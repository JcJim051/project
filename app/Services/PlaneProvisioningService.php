<?php

namespace App\Services;

use App\Models\OperationalActivityMapping;
use App\Models\OperationalActivityEvent;
use App\Models\OperationalCycle;
use App\Models\OperationalLabel;
use App\Models\OperationalModule;
use App\Models\OperationalState;
use App\Models\PlaneConnection;
use App\Models\PlaneTaskLink;
use App\Models\ProfesionalAmbiental;
use App\Models\Project;
use App\Models\ProjectPlaneCycle;
use App\Models\ProjectPlaneLabel;
use App\Models\Specialist;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PlaneProvisioningService
{
    public function __construct(
        private readonly OperationalActivityMappingService $mappingService,
    ) {
    }

    public function activeConnection(): ?PlaneConnection
    {
        return PlaneConnection::query()->where('activo', true)->latest('id')->first();
    }

    public function testConnection(?PlaneConnection $connection = null): array
    {
        $connection ??= $this->activeConnection();
        if (! $connection) {
            return [
                'success' => false,
                'status' => 'missing_connection',
                'message' => 'No existe una conexión Plane activa.',
            ];
        }

        try {
            $request = $this->authorizedRequest($connection);
            $path = $connection->healthcheck_path ?: $connection->projects_path;
            $response = $request->get($this->interpolatedUrl($connection, $path ?: '', $this->baseReplacements($connection)));

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => 'connected',
                    'message' => 'Conexión correcta con Plane.',
                    'http_status' => $response->status(),
                ];
            }

            return [
                'success' => false,
                'status' => in_array($response->status(), [401, 403], true) ? 'auth_error' : 'http_error',
                'message' => 'Plane respondió con estado ' . $response->status() . '.',
                'http_status' => $response->status(),
                'body' => $response->body(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'network_error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function provisionProject(Project $project): array
    {
        $this->mappingService->ensureDefaults();

        $connection = $this->activeConnection();
        if (! $connection) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'No existe una conexión Plane activa para provisionar el proyecto.',
            ];
        }

        $project->forceFill([
            'plane_connection_id' => $connection->id,
            'plane_sync_status' => 'pending',
            'plane_last_error' => null,
        ])->save();

        $planeProjectId = (string) $project->plane_project_id;
        $planeProjectUrl = (string) ($project->plane_project_url ?? '');

        try {
            $request = $this->authorizedRequest($connection);

            if ($planeProjectId !== '' && ! $this->planeProjectExists($request, $connection, $planeProjectId, $project)) {
                $planeProjectId = '';
                $planeProjectUrl = '';
                $reboundProject = $this->findExistingPlaneProject($request, $connection, $project);
                if ($reboundProject !== null) {
                    $planeProjectId = (string) ($reboundProject['id'] ?? '');
                    $planeProjectUrl = $this->extractProjectUrl($reboundProject, $connection, $planeProjectId);
                }

                if ($planeProjectId === '') {
                    $this->resetPlaneTaskLinks($project);
                }
            }

            if ($planeProjectId === '') {
                $projectResponse = $request->post(
                    $this->interpolatedUrl($connection, $connection->projects_path, $this->baseReplacements($connection)),
                    $this->projectPayload($project)
                );

                if (! $projectResponse->successful()) {
                    if ($projectResponse->status() === 409) {
                        $existingProject = $this->findExistingPlaneProject($request, $connection, $project);
                        if ($existingProject !== null) {
                            $planeProjectId = (string) ($existingProject['id'] ?? '');
                            if ($planeProjectId !== '') {
                                $planeProjectUrl = $this->extractProjectUrl($existingProject, $connection, $planeProjectId);
                                $this->resetPlaneTaskLinks($project);
                            }
                        }
                    }

                    if ($planeProjectId === '') {
                        $body = trim((string) $projectResponse->body());

                        return [
                            'success' => false,
                            'status' => 'failed',
                            'message' => 'No se pudo crear el proyecto en Plane. HTTP ' . $projectResponse->status()
                                . ($body !== '' ? ' · ' . Str::limit($body, 300) : ''),
                            'response_body' => $projectResponse->body(),
                        ];
                    }
                }

                if ($planeProjectId === '') {
                    $createdProject = $projectResponse->json();
                    $planeProjectId = $this->extractProjectId($createdProject) ?: '';
                    if ($planeProjectId === '') {
                        return [
                            'success' => false,
                            'status' => 'failed',
                            'message' => 'Plane no devolvió un identificador de proyecto reconocido.',
                            'response_body' => $projectResponse->body(),
                        ];
                    }

                    $planeProjectUrl = $this->extractProjectUrl($createdProject, $connection, $planeProjectId);
                }
            }

            $this->syncProjectSettings($request, $connection, $planeProjectId);
            $states = $this->syncStates($request, $connection, $planeProjectId, $project);
            $modules = $this->syncModules($request, $connection, $planeProjectId, $project);
            $labels = $this->syncLabels($request, $connection, $planeProjectId, $project);
            $cycles = $this->syncCycles($request, $connection, $planeProjectId, $project);
            $this->syncTasks($request, $connection, $project, $planeProjectId, $states, $modules, $labels, $cycles);

            return [
                'success' => true,
                'status' => 'provisioned',
                'plane_project_id' => $planeProjectId,
                'plane_project_url' => $planeProjectUrl,
                'message' => $project->plane_project_id ? 'Proyecto sincronizado correctamente en Plane.' : 'Proyecto provisionado correctamente en Plane.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'plane_project_id' => $planeProjectId !== '' ? $planeProjectId : null,
                'plane_project_url' => $planeProjectUrl !== '' ? $planeProjectUrl : null,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function syncProjectTeam(Project $project): array
    {
        $this->mappingService->ensureDefaults();

        $connection = $this->activeConnection();
        if (! $connection) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'No existe una conexión Plane activa para sincronizar el equipo.',
            ];
        }

        if (blank($project->plane_project_id)) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'El proyecto aún no existe en Plane. Ejecuta primero la sincronización completa.',
            ];
        }

        $project->forceFill([
            'plane_connection_id' => $connection->id,
            'plane_sync_status' => 'pending',
            'plane_last_error' => null,
        ])->save();

        try {
            $request = $this->authorizedRequest($connection);
            $planeProjectId = (string) $project->plane_project_id;

            if (! $this->planeProjectExists($request, $connection, $planeProjectId, $project)) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'El proyecto asociado ya no existe en Plane. Ejecuta una sincronización completa.',
                ];
            }

            $memberDirectory = $this->planeMemberDirectory($request, $connection);
            $projectMembers = $this->planeProjectMemberDirectory($request, $connection, $planeProjectId);
            $teamAssigneeIds = $this->projectTeamPlaneAssigneeIds($project, $memberDirectory);

            if (! empty($teamAssigneeIds)) {
                $this->ensurePlaneProjectMembers($request, $connection, $planeProjectId, $teamAssigneeIds, $projectMembers);
            }

            return [
                'success' => true,
                'status' => 'provisioned',
                'plane_project_id' => $planeProjectId,
                'plane_project_url' => $project->resolved_plane_project_url,
                'message' => 'Equipo sincronizado correctamente con Plane.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function syncProjectTasks(Project $project): array
    {
        $this->mappingService->ensureDefaults();

        $connection = $this->activeConnection();
        if (! $connection) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'No existe una conexión Plane activa para sincronizar tareas.',
            ];
        }

        if (blank($project->plane_project_id)) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => 'El proyecto aún no existe en Plane. Ejecuta primero la sincronización completa.',
            ];
        }

        $project->forceFill([
            'plane_connection_id' => $connection->id,
            'plane_sync_status' => 'pending',
            'plane_last_error' => null,
        ])->save();

        try {
            $request = $this->authorizedRequest($connection);
            $planeProjectId = (string) $project->plane_project_id;

            if (! $this->planeProjectExists($request, $connection, $planeProjectId, $project)) {
                return [
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'El proyecto asociado ya no existe en Plane. Ejecuta una sincronización completa.',
                ];
            }

            $this->syncProjectSettings($request, $connection, $planeProjectId);
            $states = $this->syncStates($request, $connection, $planeProjectId, $project);
            $modules = $this->syncModules($request, $connection, $planeProjectId, $project);
            $labels = $this->syncLabels($request, $connection, $planeProjectId, $project);
            $cycles = $this->syncCycles($request, $connection, $planeProjectId, $project);
            $this->syncTasks($request, $connection, $project, $planeProjectId, $states, $modules, $labels, $cycles);

            return [
                'success' => true,
                'status' => 'provisioned',
                'plane_project_id' => $planeProjectId,
                'plane_project_url' => $project->resolved_plane_project_url,
                'message' => 'Tareas sincronizadas correctamente con Plane.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function syncSpecialistsAgainstPlane(iterable $specialists): array
    {
        $connection = $this->activeConnection();
        if (! $connection) {
            return [
                'success' => false,
                'message' => 'No existe una conexión Plane activa.',
            ];
        }

        $items = collect($specialists)
            ->filter(fn ($specialist) => $specialist instanceof Specialist)
            ->unique(fn (Specialist $specialist) => $specialist->getKey())
            ->values();

        if ($items->isEmpty()) {
            return [
                'success' => true,
                'message' => 'No había especialistas para sincronizar.',
            ];
        }

        try {
            $directory = $this->planeMemberDirectory($this->authorizedRequest($connection), $connection);
            $items->each(function (Specialist $specialist) use ($directory) {
                $fresh = $specialist->fresh() ?? $specialist;
                $email = $this->entityEmail($fresh);
                $planeAssignee = $email !== '' ? ($directory[$email] ?? null) : null;
                $this->syncSpecialistPlaneReference($fresh, $planeAssignee, $email);
            });

            return [
                'success' => true,
                'message' => 'Especialistas sincronizados contra Plane.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    public function inviteSpecialistToWorkspace(Specialist $specialist): array
    {
        $connection = $this->activeConnection();
        if (! $connection) {
            $this->syncSpecialistPlaneReference($specialist, null, '', 'No existe una conexión Plane activa.', 'error');

            return [
                'success' => false,
                'status' => 'missing_connection',
                'message' => 'No existe una conexión Plane activa.',
            ];
        }

        $email = $this->entityEmail($specialist);
        if ($email === '') {
            $this->syncSpecialistPlaneReference($specialist, null, '', 'El especialista no tiene correo válido para invitarlo a Plane.', 'error');

            return [
                'success' => false,
                'status' => 'missing_email',
                'message' => 'El especialista no tiene correo válido para invitarlo a Plane.',
            ];
        }

        try {
            $request = $this->authorizedRequest($connection);
            $memberDirectory = $this->planeMemberDirectory($request, $connection);
            $existingMember = $memberDirectory[$email] ?? null;
            if ($existingMember) {
                $this->syncSpecialistPlaneReference($specialist, $existingMember, $email, null, 'linked');

                return [
                    'success' => true,
                    'status' => 'linked',
                    'message' => 'El especialista ya existe como miembro activo del workspace en Plane.',
                    'plane_user_id' => $existingMember['plane_user_id'] ?? null,
                ];
            }

            $response = $this->planeWriteWithRetry(
                $request,
                'post',
                $this->interpolatedUrl($connection, '/api/v1/workspaces/{workspace_slug}/invitations/', $this->baseReplacements($connection)),
                [
                    'email' => $email,
                    'role' => 15,
                ]
            );

            if (! $response->successful() && ! in_array($response->status(), [400, 409, 422], true)) {
                $body = trim((string) $response->body());
                throw new \RuntimeException(
                    'Plane respondió con estado ' . $response->status()
                    . ($body !== '' ? ' · ' . Str::limit($body, 300) : '')
                );
            }

            $refreshedDirectory = $this->planeMemberDirectory($request, $connection);
            $member = $refreshedDirectory[$email] ?? null;

            if ($member) {
                $this->syncSpecialistPlaneReference($specialist, $member, $email, null, 'linked');

                return [
                    'success' => true,
                    'status' => 'linked',
                    'message' => 'El especialista quedó vinculado correctamente con Plane.',
                    'plane_user_id' => $member['plane_user_id'] ?? null,
                ];
            }

            $message = $response->successful()
                ? 'La invitación fue creada, pero el especialista aún no figura como miembro activo del workspace en Plane.'
                : 'El especialista ya existe o ya fue invitado en Plane, pendiente de aceptación o activación.';

            $this->syncSpecialistPlaneReference($specialist, null, $email, $message, 'invited');

            return [
                'success' => true,
                'status' => 'invited',
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            $this->syncSpecialistPlaneReference($specialist, null, $email, $e->getMessage(), 'error');

            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function inviteUserToWorkspace(User $user): array
    {
        $connection = $this->activeConnection();
        if (! $connection) {
            $this->syncUserPlaneReference($user, null, 'No existe una conexión Plane activa.', 'error');

            return [
                'success' => false,
                'status' => 'missing_connection',
                'message' => 'No existe una conexión Plane activa.',
            ];
        }

        $email = $this->entityEmail($user);
        if ($email === '') {
            $this->syncUserPlaneReference($user, null, 'El usuario no tiene correo válido para invitarlo a Plane.', 'error');

            return [
                'success' => false,
                'status' => 'missing_email',
                'message' => 'El usuario no tiene correo válido para invitarlo a Plane.',
            ];
        }

        try {
            $request = $this->authorizedRequest($connection);
            $memberDirectory = $this->planeMemberDirectory($request, $connection);
            $existingMember = $memberDirectory[$email] ?? null;
            if ($existingMember) {
                $this->syncUserPlaneReference($user, $existingMember, null, 'linked');

                return [
                    'success' => true,
                    'status' => 'linked',
                    'message' => 'El usuario ya existe como miembro activo del workspace en Plane.',
                    'plane_user_id' => $existingMember['plane_user_id'] ?? null,
                ];
            }

            $response = $this->planeWriteWithRetry(
                $request,
                'post',
                $this->interpolatedUrl($connection, '/api/v1/workspaces/{workspace_slug}/invitations/', $this->baseReplacements($connection)),
                [
                    'email' => $email,
                    'role' => 15,
                ]
            );

            if (! $response->successful() && ! in_array($response->status(), [400, 409, 422], true)) {
                $body = trim((string) $response->body());
                throw new \RuntimeException(
                    'Plane respondió con estado ' . $response->status()
                    . ($body !== '' ? ' · ' . Str::limit($body, 300) : '')
                );
            }

            $refreshedDirectory = $this->planeMemberDirectory($request, $connection);
            $member = $refreshedDirectory[$email] ?? null;

            if ($member) {
                $this->syncUserPlaneReference($user, $member, null, 'linked');

                return [
                    'success' => true,
                    'status' => 'linked',
                    'message' => 'El usuario quedó vinculado correctamente con Plane.',
                    'plane_user_id' => $member['plane_user_id'] ?? null,
                ];
            }

            $message = $response->successful()
                ? 'La invitación fue creada, pero el usuario aún no figura como miembro activo del workspace en Plane.'
                : ('El usuario ya existe o ya fue invitado en Plane, pendiente de aceptación o activación.');

            $this->syncUserPlaneReference($user, null, $message, 'invited');

            return [
                'success' => true,
                'status' => 'invited',
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            $this->syncUserPlaneReference($user, null, $e->getMessage(), 'error');

            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function syncUsersAgainstPlane(iterable $users): array
    {
        $connection = $this->activeConnection();
        if (! $connection) {
            return [
                'success' => false,
                'status' => 'missing_connection',
                'message' => 'No existe una conexión Plane activa.',
            ];
        }

        try {
            $directory = $this->planeMemberDirectory($this->authorizedRequest($connection), $connection);

            foreach ($users as $user) {
                if (! $user instanceof User) {
                    continue;
                }

                $fresh = $user->fresh() ?? $user;
                $email = $this->entityEmail($fresh);
                $planeMember = $email !== '' ? ($directory[$email] ?? null) : null;
                $message = $email === ''
                    ? 'El usuario no tiene correo válido para buscarlo en Plane.'
                    : ($planeMember ? null : 'No se encontró el usuario como miembro activo del workspace en Plane.');
                $status = $planeMember ? 'linked' : ($email === '' ? 'error' : 'not_found');

                $this->syncUserPlaneReference($fresh, $planeMember, $message, $status);
            }

            return [
                'success' => true,
                'status' => 'synced',
                'message' => 'Usuarios sincronizados contra Plane.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    public function projectTeamStatus(Project $project): array
    {
        $connection = $this->activeConnection();
        if (! $connection) {
            return [
                'success' => false,
                'status' => 'missing_connection',
                'message' => 'No existe una conexión Plane activa.',
                'members' => [],
                'found_count' => 0,
                'missing_count' => 0,
                'in_project_count' => 0,
            ];
        }

        $relations = ['formulador', 'estructurador', 'profesionalAmbiental'];
        if (Schema::hasTable('project_study_specialist_assignments')) {
            if (Schema::hasTable('specialists')) {
                $relations[] = 'studySpecialistAssignments.specialist';
            }
            $relations[] = 'studySpecialistAssignments.user';
        }
        $project->loadMissing($relations);

        try {
            $request = $this->authorizedRequest($connection);
            $workspaceMembers = $this->planeMemberDirectory($request, $connection);
            $projectMembers = filled($project->plane_project_id)
                ? $this->planeProjectMemberDirectory($request, $connection, (string) $project->plane_project_id)
                : [];

            $items = collect([
                [
                    'role' => 'Formulador',
                    'entity' => $project->formulador,
                    'source' => null,
                ],
                [
                    'role' => 'Estructurador',
                    'entity' => $project->estructurador,
                    'source' => null,
                ],
                [
                    'role' => 'Apoyo ambiental',
                    'entity' => $this->environmentalSupportContact($project),
                    'source' => null,
                ],
            ]);

            if ($project->relationLoaded('studySpecialistAssignments')) {
                $items = $items->concat(
                    $project->studySpecialistAssignments->map(function ($assignment) {
                        return [
                            'role' => 'Especialista',
                            'entity' => $assignment->specialist ?: $assignment->user,
                            'source' => (string) ($assignment->study_folder ?? ''),
                        ];
                    })
                );
            }

            $members = $items
                ->filter(fn (array $item) => filled($item['entity']))
                ->map(function (array $item) use ($workspaceMembers, $projectMembers) {
                    $entity = $item['entity'];
                    $email = $this->entityEmail($entity);
                    $planeMember = $email !== '' ? ($workspaceMembers[$email] ?? null) : null;

                    if ($entity instanceof Specialist) {
                        $this->syncSpecialistPlaneReference($entity, $planeMember, $email);
                    }

                    $planeUserId = (string) ($planeMember['plane_user_id'] ?? '');
                    $inProject = $planeUserId !== '' && isset($projectMembers[$planeUserId]);

                    return [
                        'role' => $item['role'],
                        'source' => $item['source'],
                        'name' => (string) ($entity->nombre ?? $entity->name ?? 'Sin nombre'),
                        'email' => $email,
                        'found_in_workspace' => $planeMember !== null,
                        'in_project' => $inProject,
                        'plane_user_id' => $planeUserId !== '' ? $planeUserId : null,
                        'note' => $planeMember
                            ? ($inProject ? 'Listo en el proyecto Plane.' : 'Existe en Plane, pendiente de quedar agregado al proyecto.')
                            : 'No existe en el workspace de Plane con este correo.',
                    ];
                })
                ->values();

            return [
                'success' => true,
                'status' => 'ok',
                'message' => 'Equipo revisado contra Plane.',
                'members' => $members->all(),
                'found_count' => $members->where('found_in_workspace', true)->count(),
                'missing_count' => $members->where('found_in_workspace', false)->count(),
                'in_project_count' => $members->where('in_project', true)->count(),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'status' => 'error',
                'message' => $e->getMessage(),
                'members' => [],
                'found_count' => 0,
                'missing_count' => 0,
                'in_project_count' => 0,
            ];
        }
    }

    private function syncStates(
        PendingRequest $request,
        PlaneConnection $connection,
        string $planeProjectId,
        ?Project $project = null
    ): Collection {
        $states = collect($this->fetchPlaneStates($request, $connection, $planeProjectId));

        if ($states->isEmpty()) {
            throw new \RuntimeException('Plane no devolvió estados para el proyecto provisionado.');
        }

        if (! $project) {
            return $states;
        }

        if (filled($project->plane_states_seeded_at) && $this->hasCompleteOrbitStateSeed($states)) {
            return $states;
        }

        if (filled($project->plane_states_seeded_at)) {
            Log::warning('plane_state_seed_incomplete_detected', [
                'project_id' => $project->id,
                'plane_project_id' => $planeProjectId,
                'state_names' => $states->pluck('name')->values()->all(),
            ]);
        }

        $states = $this->seedPlaneStates($request, $connection, $planeProjectId, $states);

        $project->forceFill(['plane_states_seeded_at' => now()])->save();

        return $states;
    }

    private function seedPlaneStates(
        PendingRequest $request,
        PlaneConnection $connection,
        string $planeProjectId,
        Collection $existingStates
    ): Collection {
        $catalog = OperationalState::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        if ($catalog->isEmpty()) {
            throw new \RuntimeException('No hay estados operativos activos configurados en Orbit.');
        }

        $pending = $catalog->firstWhere('codigo', 'pendiente');
        if (! $pending) {
            throw new \RuntimeException('El catálogo debe tener activo el estado Pendiente antes de provisionar Plane.');
        }

        // Pendiente is created first and becomes Plane's default. This releases
        // the native Backlog state so it can be removed safely afterwards.
        $orderedCatalog = collect([$pending])->concat($catalog->where('id', '!=', $pending->id));
        $states = $existingStates;

        foreach ($orderedCatalog as $state) {
            $matched = $this->findOrbitPlaneState($states, $state);

            if ($state->codigo === 'pendiente' && $matched && ! (bool) ($matched['default'] ?? false)) {
                $this->deletePlaneState($request, $connection, $planeProjectId, (string) $matched['id'], $state->nombre);
                $states = collect($this->fetchPlaneStates($request, $connection, $planeProjectId));
                $matched = null;
            }

            $payload = $this->statePayload($state);
            if ($matched) {
                $this->updatePlaneState($request, $connection, $planeProjectId, (string) $matched['id'], $payload, $state->nombre);
                continue;
            }

            $response = $this->planeWriteWithRetry(
                $request,
                'post',
                $this->interpolatedUrl($connection, $connection->states_path_template, [
                    ...$this->baseReplacements($connection),
                    'project_id' => $planeProjectId,
                ]),
                $payload
            );

            if (! $response->successful()) {
                $this->throwPlaneStateHttpError('crear', $planeProjectId, $state->nombre, $response);
            }

            $states = collect($this->fetchPlaneStates($request, $connection, $planeProjectId));
        }

        $states = collect($this->fetchPlaneStates($request, $connection, $planeProjectId));
        foreach ($catalog as $state) {
            $matched = $this->findOrbitPlaneState($states, $state);
            if (! $matched) {
                throw new \RuntimeException('Plane no confirmó la creación del estado [' . $state->nombre . '].');
            }

            // Plane recalculates sequence on create, so normalize it once all
            // Orbit states exist to preserve the order configured in the front.
            $this->updatePlaneState(
                $request,
                $connection,
                $planeProjectId,
                (string) $matched['id'],
                $this->statePayload($state),
                $state->nombre
            );
        }

        $states = collect($this->fetchPlaneStates($request, $connection, $planeProjectId));
        $pendingStateId = (string) (($this->findOrbitPlaneState($states, $pending)['id'] ?? ''));
        if ($pendingStateId !== '') {
            $this->moveIssuesFromNativeStates(
                $request,
                $connection,
                $planeProjectId,
                $states->filter(fn (array $state): bool => $this->isNativePlaneBootstrapState($state))
                    ->pluck('id')
                    ->filter()
                    ->map(fn ($id): string => (string) $id)
                    ->values()
                    ->all(),
                $pendingStateId
            );
            $states = collect($this->fetchPlaneStates($request, $connection, $planeProjectId));
        }

        foreach ($states->filter(fn (array $state): bool => $this->isNativePlaneBootstrapState($state)) as $nativeState) {
            $this->deletePlaneState(
                $request,
                $connection,
                $planeProjectId,
                (string) $nativeState['id'],
                (string) $nativeState['name']
            );
        }

        $states = collect($this->fetchPlaneStates($request, $connection, $planeProjectId));
        $missing = $catalog->filter(fn (OperationalState $state): bool => ! $this->findOrbitPlaneState($states, $state));
        if ($missing->isNotEmpty()) {
            throw new \RuntimeException('La semilla de estados quedó incompleta en Plane: ' . $missing->pluck('nombre')->implode(', ') . '.');
        }

        if ($states->contains(fn (array $state): bool => $this->isNativePlaneBootstrapState($state))) {
            throw new \RuntimeException('Plane conservó estados nativos en inglés después de la semilla.');
        }

        $defaultState = $this->findOrbitPlaneState($states, $pending);
        if (! $defaultState || ! (bool) ($defaultState['default'] ?? false)) {
            throw new \RuntimeException('El estado Pendiente no quedó configurado como predeterminado en Plane.');
        }

        return $states;
    }

    private function moveIssuesFromNativeStates(
        PendingRequest $request,
        PlaneConnection $connection,
        string $planeProjectId,
        array $nativeStateIds,
        string $targetStateId
    ): void {
        $nativeStateIds = collect($nativeStateIds)->filter()->values()->all();
        if ($nativeStateIds === [] || $targetStateId === '') {
            return;
        }

        foreach ($this->fetchPlaneIssues($request, $connection, $planeProjectId) as $issue) {
            $issueId = (string) ($issue['id'] ?? '');
            $stateId = (string) ($issue['state'] ?? '');

            if ($issueId === '' || ! in_array($stateId, $nativeStateIds, true)) {
                continue;
            }

            $response = $this->planeWriteWithRetry(
                $request,
                'patch',
                $this->interpolatedUrl($connection, $connection->issue_detail_path_template ?: '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/issues/{issue_id}/', [
                    ...$this->baseReplacements($connection),
                    'project_id' => $planeProjectId,
                    'issue_id' => $issueId,
                ]),
                ['state' => $targetStateId]
            );

            if ($response->successful()) {
                continue;
            }

            throw new \RuntimeException('No se pudo mover la tarea [' . $issueId . '] fuera de un estado nativo de Plane. HTTP ' . $response->status() . ($response->body() ? ' · ' . Str::limit($response->body(), 300) : ''));
        }
    }

    private function syncModules(PendingRequest $request, PlaneConnection $connection, string $planeProjectId, Project $project): Collection
    {
        $modules = $this->planeModuleDefinitions($project);
        $existingModules = $this->fetchPlaneModules($request, $connection, $planeProjectId);
        $usedModuleIds = [];

        foreach ($modules as $module) {
            $payload = $this->modulePayload($module);

            $matched = collect($existingModules)->first(function (array $existing) use ($module, $usedModuleIds): bool {
                return ($existing['external_source'] ?? null) === 'orbit'
                    && (string) ($existing['external_id'] ?? '') === (string) $module['external_id']
                    && ! in_array((string) $existing['id'], $usedModuleIds, true);
            });

            if ($matched) {
                $this->updatePlaneModule($request, $connection, $planeProjectId, (string) $matched['id'], $payload, $module['name']);
                $usedModuleIds[] = (string) $matched['id'];
                continue;
            }

            $response = $request->post(
                $this->interpolatedUrl($connection, $connection->modules_path_template, [
                    ...$this->baseReplacements($connection),
                    'project_id' => $planeProjectId,
                ]),
                $payload
            );

            if (! $response->successful()) {
                throw new \RuntimeException('No se pudo crear el módulo [' . $module['name'] . '] en Plane. HTTP ' . $response->status());
            }
        }

        return collect($this->fetchPlaneModules($request, $connection, $planeProjectId))
            ->keyBy(fn (array $module) => (string) ($module['external_id'] ?? 'module:' . ($module['id'] ?? '')));
    }

    private function syncLabels(PendingRequest $request, PlaneConnection $connection, string $planeProjectId, Project $project): Collection
    {
        $catalog = OperationalLabel::query()->where('activo', true)->orderBy('orden')->get();
        $existing = collect($this->fetchPlaneCatalog($request, $connection, $connection->labels_path_template, $planeProjectId, 'etiquetas'));
        $links = $project->planeLabels()->get()->keyBy('operational_label_id');
        $resolved = collect();

        foreach ($catalog as $label) {
            $link = $links->get($label->id);
            $matched = $link?->plane_label_id
                ? $existing->firstWhere('id', $link->plane_label_id)
                : null;
            $matched ??= $existing->first(fn (array $item) => (string) ($item['name'] ?? '') === (string) $label->nombre);
            $payload = [
                'name' => $label->nombre,
                'description' => (string) ($label->descripcion ?: ''),
                'color' => (string) ($label->color ?: '#64748B'),
                'sort_order' => (float) ($label->orden * 10000),
            ];

            if ($matched) {
                $response = $this->planeWriteWithRetry($request, 'patch', $this->interpolatedUrl($connection, rtrim($connection->labels_path_template, '/') . '/{label_id}/', [
                    ...$this->baseReplacements($connection), 'project_id' => $planeProjectId, 'label_id' => (string) $matched['id'],
                ]), $payload);
                if (! $response->successful()) {
                    throw new \RuntimeException('No se pudo actualizar la etiqueta [' . $label->nombre . '] en Plane. HTTP ' . $response->status());
                }
                $planeLabelId = (string) $matched['id'];
            } else {
                $response = $this->planeWriteWithRetry($request, 'post', $this->interpolatedUrl($connection, $connection->labels_path_template, [
                    ...$this->baseReplacements($connection), 'project_id' => $planeProjectId,
                ]), $payload);
                if (! $response->successful()) {
                    throw new \RuntimeException('No se pudo crear la etiqueta [' . $label->nombre . '] en Plane. HTTP ' . $response->status() . ($response->body() ? ' · ' . Str::limit($response->body(), 300) : ''));
                }
                $planeLabelId = (string) ($response->json('id') ?? '');
            }

            if ($planeLabelId === '') {
                throw new \RuntimeException('Plane no devolvió el ID de la etiqueta [' . $label->nombre . '].');
            }

            ProjectPlaneLabel::query()->updateOrCreate(
                ['project_id' => $project->id, 'operational_label_id' => $label->id],
                ['plane_label_id' => $planeLabelId, 'plane_project_id' => $planeProjectId, 'name_snapshot' => $label->nombre, 'status' => 'active', 'sync_error' => null, 'last_synced_at' => now()]
            );
            $resolved->put((string) $label->id, $planeLabelId);
        }

        return $resolved;
    }

    private function syncCycles(PendingRequest $request, PlaneConnection $connection, string $planeProjectId, Project $project): Collection
    {
        $catalog = OperationalCycle::query()->where('activo', true)->orderBy('orden')->get();
        if ($catalog->isEmpty()) {
            return collect();
        }

        $memberDirectory = $this->planeMemberDirectory($request, $connection);
        $projectMembers = $this->planeProjectMemberDirectory($request, $connection, $planeProjectId);
        $teamIds = $this->projectTeamPlaneAssigneeIds($project, $memberDirectory);
        if ($teamIds !== []) {
            $this->ensurePlaneProjectMembers($request, $connection, $planeProjectId, $teamIds, $projectMembers);
        }

        $existing = collect($this->fetchPlaneCatalog($request, $connection, $connection->cycles_path_template, $planeProjectId, 'ciclos'));
        $links = $project->planeCycles()->get()->keyBy('operational_cycle_id');
        $resolved = collect();

        foreach ($catalog as $cycle) {
            [$startDate, $endDate] = $this->resolveCycleDates($project, $cycle);
            $owner = $this->resolveTaskAssignee($project, ['responsible_type' => $cycle->owner_role, 'source_folder' => null], $memberDirectory);
            $ownerId = (string) (collect($owner['plane_assignee_ids'] ?? [])->first() ?: array_key_first($projectMembers) ?: '');
            $link = $links->get($cycle->id);
            $matched = $link?->plane_cycle_id ? $existing->firstWhere('id', $link->plane_cycle_id) : null;
            $matched ??= $existing->first(fn (array $item) => (string) ($item['external_source'] ?? '') === 'orbit' && (string) ($item['external_id'] ?? '') === 'orbit-cycle:' . $cycle->id);
            $matched ??= $existing->first(fn (array $item) => (string) ($item['name'] ?? '') === (string) $cycle->nombre);

            if ($matched) {
                $response = $this->syncPlaneCycle($request, $connection, $planeProjectId, $cycle, $startDate, $endDate, $ownerId, (string) $matched['id']);
                $planeCycleId = (string) $matched['id'];
            } else {
                $response = $this->syncPlaneCycle($request, $connection, $planeProjectId, $cycle, $startDate, $endDate, $ownerId);
                $planeCycleId = (string) ($response->json('id') ?? $response->json('results.id') ?? '');
            }

            if (
                $matched
                && $planeCycleId !== ''
                && $this->planeCycleEditRejectedBecauseCompleted($response)
            ) {
                ProjectPlaneCycle::query()->updateOrCreate(
                    ['project_id' => $project->id, 'operational_cycle_id' => $cycle->id],
                    [
                        'plane_cycle_id' => $planeCycleId,
                        'plane_project_id' => $planeProjectId,
                        'name_snapshot' => $cycle->nombre,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => 'active',
                        'sync_error' => 'Plane marcó este ciclo como completado y ya no permite editarlo. Orbit reutilizó el ciclo existente sin frenar la sincronización.',
                        'last_synced_at' => now(),
                    ]
                );
                $resolved->put((string) $cycle->id, $planeCycleId);
                continue;
            }

            if (! $response->successful() || $planeCycleId === '') {
                $body = trim((string) $response->body());
                throw new \RuntimeException('No se pudo sincronizar el ciclo [' . $cycle->nombre . '] en Plane. HTTP ' . $response->status() . ($body !== '' ? ' · ' . Str::limit($body, 300) : ''));
            }

            ProjectPlaneCycle::query()->updateOrCreate(
                ['project_id' => $project->id, 'operational_cycle_id' => $cycle->id],
                ['plane_cycle_id' => $planeCycleId, 'plane_project_id' => $planeProjectId, 'name_snapshot' => $cycle->nombre, 'start_date' => $startDate, 'end_date' => $endDate, 'status' => 'active', 'sync_error' => null, 'last_synced_at' => now()]
            );
            $resolved->put((string) $cycle->id, $planeCycleId);
        }

        return $resolved;
    }

    private function syncPlaneCycle(
        PendingRequest $request,
        PlaneConnection $connection,
        string $planeProjectId,
        OperationalCycle $cycle,
        string $startDate,
        string $endDate,
        string $ownerId = '',
        ?string $planeCycleId = null
    ): Response {
        $endpoint = $planeCycleId
            ? $this->interpolatedUrl($connection, rtrim($connection->cycles_path_template, '/') . '/{cycle_id}/', [
                ...$this->baseReplacements($connection),
                'project_id' => $planeProjectId,
                'cycle_id' => $planeCycleId,
            ])
            : $this->interpolatedUrl($connection, $connection->cycles_path_template, [
                ...$this->baseReplacements($connection),
                'project_id' => $planeProjectId,
            ]);

        $method = $planeCycleId ? 'patch' : 'post';
        $lastResponse = null;

        foreach ($this->cyclePayloadVariants($cycle, $planeProjectId, $startDate, $endDate, $ownerId) as $payload) {
            $lastResponse = $this->planeWriteWithRetry($request, $method, $endpoint, $payload);
            if ($lastResponse->successful()) {
                return $lastResponse;
            }

            if (! in_array($lastResponse->status(), [400, 401, 403, 404, 409, 422], true)) {
                return $lastResponse;
            }
        }

        return $lastResponse ?? Http::response([], 500);
    }

    private function planeCycleEditRejectedBecauseCompleted(?Response $response): bool
    {
        if (! $response || $response->status() !== 400) {
            return false;
        }

        $body = Str::lower((string) $response->body());

        return str_contains($body, 'cycle has already been completed')
            || str_contains($body, 'cannot be edited');
    }

    private function syncTasks(
        PendingRequest $request,
        PlaneConnection $connection,
        Project $project,
        string $planeProjectId,
        Collection $states,
        Collection $modules,
        Collection $labels,
        Collection $cycles
    ): void {
        $blueprints = $this->taskBlueprints($project);
        $existingLinks = $project->planeTaskLinks()->get()->keyBy('dedupe_key');
        $pendingStateId = $this->resolvePlaneStateId($states, 'pendiente');
        $completedStateId = $this->resolvePlaneStateId($states, 'completado');
        $blockedStateId = $this->resolvePlaneStateId($states, 'bloqueado') ?: $this->resolvePlaneStateId($states, 'completado');
        $memberDirectory = $this->planeMemberDirectory($request, $connection);
        $projectMembers = $this->planeProjectMemberDirectory($request, $connection, $planeProjectId);
        [$issuesByExternalId, $issuesById, $issueStatesById] = $this->planeIssueDirectories($request, $connection, $planeProjectId);
        $stateCodeById = $this->planeStateCodeDirectory($states);

        $teamAssigneeIds = $this->projectTeamPlaneAssigneeIds($project, $memberDirectory);
        if (! empty($teamAssigneeIds)) {
            $this->ensurePlaneProjectMembers($request, $connection, $planeProjectId, $teamAssigneeIds, $projectMembers);
        }

        foreach ($blueprints as $blueprint) {
            $moduleLookupKey = (string) ($blueprint['module_lookup_key'] ?? '');
            if ($moduleLookupKey === '') {
                continue;
            }

            $planeModule = $modules->get($moduleLookupKey);
            $planeModuleId = (string) ($planeModule['id'] ?? '');
            if ($planeModuleId === '') {
                throw new \RuntimeException('No se encontró el módulo operativo [' . ($blueprint['module_name'] ?? $moduleLookupKey) . '] en Plane para crear tareas.');
            }

            $assignment = $this->resolveTaskAssignee($project, $blueprint, $memberDirectory);
            $planeLabelIds = collect($blueprint['operational_label_ids'] ?? [])
                ->map(fn ($id) => $labels->get((string) $id))
                ->filter()
                ->values()
                ->all();
            $planeCycleId = (string) ($cycles->get((string) ($blueprint['operational_cycle_id'] ?? '')) ?? '');
            $desiredStateCode = $this->desiredPlaneStateCode($blueprint);
            $desiredStateId = $this->desiredPlaneStateId($desiredStateCode, $pendingStateId, $completedStateId);
            if (! empty($assignment['plane_assignee_ids'])) {
                try {
                    $this->ensurePlaneProjectMembers($request, $connection, $planeProjectId, $assignment['plane_assignee_ids'], $projectMembers);
                } catch (\Throwable $memberError) {
                    $assignment['assignment_note'] = trim(($assignment['assignment_note'] ? $assignment['assignment_note'] . ' ' : '') . 'No fue posible vincular el responsable al proyecto en Plane: ' . $memberError->getMessage());
                    $assignment['resolved_plane_assignee_id'] = null;
                    $assignment['plane_assignee_ids'] = null;
                }
            }

            /** @var PlaneTaskLink|null $link */
            $link = $existingLinks->get($blueprint['dedupe_key']);
            $matchedIssue = $issuesByExternalId[$blueprint['dedupe_key']] ?? null;
            $linkedIssueExists = $link?->plane_issue_id ? isset($issuesById[$link->plane_issue_id]) : false;

            if ((! $link || ! $link->plane_issue_id || ! $linkedIssueExists) && $matchedIssue) {
                PlaneTaskLink::query()->updateOrCreate(
                    [
                        'project_id' => $project->id,
                        'dedupe_key' => $blueprint['dedupe_key'],
                    ],
                    [
                        'operational_module_id' => $blueprint['operational_module_id'],
                        'operational_activity_mapping_id' => $blueprint['mapping_id'],
                        'requirement_id' => $blueprint['requirement_id'],
                        'plane_project_id' => $planeProjectId,
                        'plane_issue_id' => $matchedIssue['id'],
                        'plane_module_id' => $planeModuleId,
                        'source_type' => $blueprint['source_type'],
                        'source_origin' => $blueprint['source_origin'],
                        'source_folder' => $blueprint['source_folder'],
                        'source_title' => $blueprint['source_title'],
                        'title' => $blueprint['title'],
                        'plane_priority' => $blueprint['plane_priority'],
                        'responsible_type' => $blueprint['responsible_type'],
                        'resolved_user_id' => $assignment['resolved_user_id'],
                        'resolved_user_email' => $assignment['resolved_user_email'],
                        'resolved_plane_assignee_id' => $assignment['resolved_plane_assignee_id'],
                        'assignment_note' => $assignment['assignment_note'],
                        'operational_cycle_id' => $blueprint['operational_cycle_id'],
                        'operational_activity_type_id' => $blueprint['operational_activity_type_id'],
                        'activated_at' => $blueprint['activated_at'],
                        'planned_start_date' => $blueprint['planned_start_date'],
                        'planned_target_date' => $blueprint['planned_target_date'],
                        'plane_cycle_id' => $planeCycleId ?: null,
                        'plane_label_ids' => $planeLabelIds,
                        'current_state_code' => $desiredStateCode,
                        'completed_at' => $desiredStateCode === 'completado' ? now() : null,
                        'status' => 'active',
                        'sync_error' => ($matchedIssue['duplicate_count'] ?? 1) > 1
                            ? 'Plane ya tenía tareas duplicadas para esta actividad. Orbit reutilizó una existente y frenó nuevas duplicaciones.'
                            : null,
                        'last_synced_at' => now(),
                    ]
                );

                $link = $project->planeTaskLinks()->where('dedupe_key', $blueprint['dedupe_key'])->first();
                if ($link) {
                    $this->recordActivityEvent($link, 'activity_activated', $blueprint['activated_at'], ['origin' => 'plane_rebound']);
                }
            }

            if ($link && $link->plane_issue_id && $link->status === 'active') {
                $this->updatePlaneIssue(
                    $request,
                    $connection,
                    $planeProjectId,
                    $link->plane_issue_id,
                    $blueprint['title'],
                    $blueprint['description'],
                    $desiredStateId,
                    $planeModuleId,
                    $blueprint['dedupe_key'],
                    $blueprint['plane_priority'],
                    $assignment['plane_assignee_ids'],
                    $blueprint['planned_start_date'],
                    $blueprint['planned_target_date'],
                    $planeLabelIds
                );
                $this->assignIssueToModule($request, $connection, $planeProjectId, $planeModuleId, $link->plane_issue_id);
                $this->assignIssueToCycle($request, $connection, $planeProjectId, $planeCycleId, $link->plane_issue_id);
                $currentStateCode = $this->stateCodeByIssueId($issueStatesById, $stateCodeById, $link->plane_issue_id, $link->current_state_code);
                $link->forceFill([
                    'operational_module_id' => $blueprint['operational_module_id'],
                    'operational_activity_mapping_id' => $blueprint['mapping_id'],
                    'requirement_id' => $blueprint['requirement_id'],
                    'plane_module_id' => $planeModuleId,
                    'title' => $blueprint['title'],
                    'source_folder' => $blueprint['source_folder'],
                    'source_title' => $blueprint['source_title'],
                    'plane_priority' => $blueprint['plane_priority'],
                    'responsible_type' => $blueprint['responsible_type'],
                    'resolved_user_id' => $assignment['resolved_user_id'],
                    'resolved_user_email' => $assignment['resolved_user_email'],
                    'resolved_plane_assignee_id' => $assignment['resolved_plane_assignee_id'],
                    'assignment_note' => $assignment['assignment_note'],
                    'operational_cycle_id' => $blueprint['operational_cycle_id'],
                    'operational_activity_type_id' => $blueprint['operational_activity_type_id'],
                    'activated_at' => $blueprint['activated_at'],
                    'planned_start_date' => $blueprint['planned_start_date'],
                    'planned_target_date' => $blueprint['planned_target_date'],
                    'plane_cycle_id' => $planeCycleId ?: null,
                    'plane_label_ids' => $planeLabelIds,
                    'current_state_code' => $desiredStateCode ?? $currentStateCode,
                    'completed_at' => $desiredStateCode === 'completado'
                        ? ($link->completed_at ?: now())
                        : ($blueprint['source_type'] === 'requirement' ? null : $link->completed_at),
                    'last_synced_at' => now(),
                    'sync_error' => $matchedIssue && (($matchedIssue['duplicate_count'] ?? 1) > 1)
                        ? 'Plane ya tenía tareas duplicadas para esta actividad. Orbit reutilizó una existente y frenó nuevas duplicaciones.'
                        : null,
                ])->save();
                $this->syncRequirementEvidenceStateEvents($link, $desiredStateCode, $currentStateCode);
                continue;
            }

            if ($link && $link->plane_issue_id && $link->status === 'discarded') {
                $this->updatePlaneIssue(
                    $request,
                    $connection,
                    $planeProjectId,
                    $link->plane_issue_id,
                    $blueprint['title'],
                    $blueprint['description'],
                    $desiredStateId ?: $pendingStateId,
                    $planeModuleId,
                    $blueprint['dedupe_key'],
                    $blueprint['plane_priority'],
                    $assignment['plane_assignee_ids'],
                    $blueprint['planned_start_date'],
                    $blueprint['planned_target_date'],
                    $planeLabelIds
                );
                $this->assignIssueToModule($request, $connection, $planeProjectId, $planeModuleId, $link->plane_issue_id);
                $this->assignIssueToCycle($request, $connection, $planeProjectId, $planeCycleId, $link->plane_issue_id);
                $link->forceFill([
                    'status' => 'active',
                    'deactivated_at' => null,
                    'operational_module_id' => $blueprint['operational_module_id'],
                    'operational_activity_mapping_id' => $blueprint['mapping_id'],
                    'requirement_id' => $blueprint['requirement_id'],
                    'plane_module_id' => $planeModuleId,
                    'title' => $blueprint['title'],
                    'source_folder' => $blueprint['source_folder'],
                    'source_title' => $blueprint['source_title'],
                    'plane_priority' => $blueprint['plane_priority'],
                    'responsible_type' => $blueprint['responsible_type'],
                    'resolved_user_id' => $assignment['resolved_user_id'],
                    'resolved_user_email' => $assignment['resolved_user_email'],
                    'resolved_plane_assignee_id' => $assignment['resolved_plane_assignee_id'],
                    'assignment_note' => $assignment['assignment_note'],
                    'operational_cycle_id' => $blueprint['operational_cycle_id'],
                    'operational_activity_type_id' => $blueprint['operational_activity_type_id'],
                    'activated_at' => $blueprint['activated_at'],
                    'planned_start_date' => $blueprint['planned_start_date'],
                    'planned_target_date' => $blueprint['planned_target_date'],
                    'plane_cycle_id' => $planeCycleId ?: null,
                    'plane_label_ids' => $planeLabelIds,
                    'current_state_code' => $desiredStateCode ?: 'pendiente',
                    'completed_at' => $desiredStateCode === 'completado' ? ($link->completed_at ?: now()) : null,
                    'last_synced_at' => now(),
                    'sync_error' => $matchedIssue && (($matchedIssue['duplicate_count'] ?? 1) > 1)
                        ? 'Plane ya tenía tareas duplicadas para esta actividad. Orbit reutilizó una existente y frenó nuevas duplicaciones.'
                        : null,
                ])->save();
                $this->recordActivityEvent($link, 'activity_reactivated', now(), ['origin' => 'orbit_sync']);
                $this->syncRequirementEvidenceStateEvents($link, $desiredStateCode, $link->current_state_code);
                continue;
            }

            $issue = $this->createPlaneIssue(
                $request,
                $connection,
                $planeProjectId,
                $blueprint['title'],
                $blueprint['description'],
                $desiredStateId ?: $pendingStateId,
                $planeModuleId,
                $blueprint['dedupe_key'],
                $blueprint['plane_priority'],
                $assignment['plane_assignee_ids'],
                $blueprint['planned_start_date'],
                $blueprint['planned_target_date'],
                $planeLabelIds
            );
            $this->assignIssueToModule($request, $connection, $planeProjectId, $planeModuleId, $issue['id']);
            $this->assignIssueToCycle($request, $connection, $planeProjectId, $planeCycleId, $issue['id']);

            $createdLink = PlaneTaskLink::query()->updateOrCreate(
                [
                    'project_id' => $project->id,
                    'dedupe_key' => $blueprint['dedupe_key'],
                ],
                [
                    'operational_module_id' => $blueprint['operational_module_id'],
                    'operational_activity_mapping_id' => $blueprint['mapping_id'],
                    'requirement_id' => $blueprint['requirement_id'],
                    'plane_project_id' => $planeProjectId,
                    'plane_issue_id' => $issue['id'],
                    'plane_module_id' => $planeModuleId,
                    'source_type' => $blueprint['source_type'],
                    'source_origin' => $blueprint['source_origin'],
                    'source_folder' => $blueprint['source_folder'],
                    'source_title' => $blueprint['source_title'],
                    'title' => $blueprint['title'],
                    'plane_priority' => $blueprint['plane_priority'],
                    'responsible_type' => $blueprint['responsible_type'],
                    'resolved_user_id' => $assignment['resolved_user_id'],
                    'resolved_user_email' => $assignment['resolved_user_email'],
                    'resolved_plane_assignee_id' => $assignment['resolved_plane_assignee_id'],
                    'assignment_note' => $assignment['assignment_note'],
                    'operational_cycle_id' => $blueprint['operational_cycle_id'],
                    'operational_activity_type_id' => $blueprint['operational_activity_type_id'],
                    'activated_at' => $blueprint['activated_at'],
                    'planned_start_date' => $blueprint['planned_start_date'],
                    'planned_target_date' => $blueprint['planned_target_date'],
                    'plane_cycle_id' => $planeCycleId ?: null,
                    'plane_label_ids' => $planeLabelIds,
                    'current_state_code' => $desiredStateCode ?: 'pendiente',
                    'completed_at' => $desiredStateCode === 'completado' ? now() : null,
                    'status' => 'active',
                    'sync_error' => null,
                    'last_synced_at' => now(),
                ]
            );
            $this->recordActivityEvent($createdLink, 'activity_activated', $blueprint['activated_at'], ['origin' => $blueprint['source_type']]);
            $this->syncRequirementEvidenceStateEvents($createdLink, $desiredStateCode, null);

            $issuesByExternalId[$blueprint['dedupe_key']] = [
                'id' => $issue['id'],
                'duplicate_count' => 1,
            ];
            $issuesById[$issue['id']] = true;
            $issueStatesById[$issue['id']] = $desiredStateId;
        }

        $activeKeys = $blueprints->pluck('dedupe_key')->all();
        foreach ($existingLinks as $dedupeKey => $link) {
            if (in_array($dedupeKey, $activeKeys, true) || $link->status === 'discarded') {
                continue;
            }

            if ($link->plane_issue_id && $blockedStateId) {
                $discardedTitle = $this->discardedTitle($link->title ?: $link->source_title ?: 'Actividad descartada');
                $this->updatePlaneIssue($request, $connection, $planeProjectId, $link->plane_issue_id, $discardedTitle, null, $blockedStateId, $link->plane_module_id, $link->dedupe_key, $link->plane_priority, null);
            }

            $link->forceFill([
                'status' => 'discarded',
                'deactivated_at' => now(),
                'sync_error' => null,
                'last_synced_at' => now(),
            ])->save();
            $this->recordActivityEvent($link, 'activity_deactivated', now(), ['origin' => 'orbit_sync']);
        }

        $this->assertGenericActivitiesSynced($project, $blueprints, $issuesByExternalId);
    }

    private function recordActivityEvent(PlaneTaskLink $link, string $eventType, $occurredAt, array $metadata = []): void
    {
        if ($eventType === 'activity_activated' && OperationalActivityEvent::query()->where('plane_task_link_id', $link->id)->where('event_type', $eventType)->exists()) {
            return;
        }

        OperationalActivityEvent::query()->create([
            'project_id' => $link->project_id,
            'plane_task_link_id' => $link->id,
            'requirement_id' => $link->requirement_id,
            'event_type' => $eventType,
            'source' => 'orbit',
            'metadata' => $metadata,
            'occurred_at' => $occurredAt ?: now(),
        ]);
    }

    private function taskBlueprints(Project $project): Collection
    {
        $this->mappingService->ensureDefaults();
        $relations = ['formulador', 'estructurador', 'profesionalAmbiental'];
        if (Schema::hasTable('project_study_specialist_assignments')) {
            if (Schema::hasTable('specialists')) {
                $relations[] = 'studySpecialistAssignments.specialist';
            }
            $relations[] = 'studySpecialistAssignments.user';
        }

        $project->loadMissing($relations);

        $requirements = $this->mappingService->applicableRequirements($project)->keyBy('id');
        $studyFolders = $this->mappingService->applicableStudyFolders($project);
        $existingLinks = $project->planeTaskLinks()->get()->keyBy('dedupe_key');
        $requirementActivations = DB::table('project_requirement')
            ->where('project_id', $project->id)
            ->pluck('activated_at', 'requirement_id');
        $projectCycles = $project->planeCycles()->get()->keyBy('operational_cycle_id');
        $requirementsCollection = $requirements->values();
        $evidenceAnalysis = app(RequirementProgressService::class)->analyze(
            $requirementsCollection,
            $project->evidences()->get()
        );
        $evidenceStatuses = $evidenceAnalysis['requirements'] ?? [];

        $requirementMappings = OperationalActivityMapping::query()
            ->with(['operationalModule', 'requirement', 'operationalLabels'])
            ->where('source_type', 'requirement')
            ->where('activo', true)
            ->where('create_automatically', true)
            ->whereIn('requirement_id', $requirements->keys())
            ->get()
            ->map(function (OperationalActivityMapping $mapping) use ($requirements, $evidenceStatuses) {
                /** @var Requirement|null $requirement */
                $requirement = $requirements->get($mapping->requirement_id);
                if (! $requirement || ! $mapping->operationalModule) {
                    return null;
                }

                $moduleLookupKey = $this->moduleLookupKeyForRequirement($mapping->operationalModule, $requirement->carpeta);
                $moduleName = $this->planeModuleNameForRequirement($mapping->operationalModule, $requirement->carpeta);

                $title = $mapping->titulo_operativo;
                $description = (string) ($mapping->descripcion_operativa ?: '');
                if ($mapping->operationalModule->codigo === '02') {
                    $title = Str::limit(($requirement->carpeta ?: 'Estudio') . ' · ' . $title, 240, '...');
                    $description = trim($description . ' Estudio: ' . ($requirement->carpeta ?: 'Sin estudio') . '.');
                }

                return collect([
                    'dedupe_key' => 'requirement:' . $requirement->id,
                    'mapping_id' => $mapping->id,
                    'module' => $mapping->operationalModule,
                    'module_lookup_key' => $moduleLookupKey,
                    'module_name' => $moduleName,
                    'operational_module_id' => $mapping->operationalModule->id,
                    'source_type' => 'requirement',
                    'source_origin' => $mapping->source_origin,
                    'source_folder' => $requirement->carpeta,
                    'source_title' => $requirement->nombre_documento ?: $requirement->requisito ?: $requirement->texto,
                    'title' => $title,
                    'description' => $description ?: 'Gestionar la actividad y dejar soporte listo para validación en Orbit.',
                    'requirement_id' => $requirement->id,
                    'plane_priority' => $mapping->plane_priority ?: 'medium',
                    'responsible_type' => $mapping->responsible_type ?: 'sin_responsable',
                    'operational_cycle_id' => $mapping->operational_cycle_id,
                    'operational_activity_type_id' => $mapping->operational_activity_type_id,
                    'operational_label_ids' => $mapping->operationalLabels->pluck('id')->all(),
                    'planned_start_rule' => $mapping->planned_start_rule ?: 'none',
                    'start_offset_days' => (int) $mapping->start_offset_days,
                    'default_duration_days' => $mapping->default_duration_days,
                    'track_as_kpi' => (bool) $mapping->track_as_kpi,
                    'mapping_created_at' => $mapping->created_at,
                    'has_valid_evidence' => (bool) ($evidenceStatuses[$requirement->id]['has_evidence'] ?? false),
                ]);
            })
            ->filter();

        $genericMappings = OperationalActivityMapping::query()
            ->with(['operationalModule', 'operationalLabels'])
            ->where('source_type', 'generic')
            ->where('activo', true)
            ->where('create_automatically', true)
            ->get()
            ->flatMap(function (OperationalActivityMapping $mapping) use ($studyFolders) {
                if (! $mapping->operationalModule) {
                    return [];
                }

                if ($mapping->repeat_per_study) {
                    return $studyFolders->map(function (string $folder) use ($mapping) {
                        return collect([
                            'dedupe_key' => 'generic:' . $mapping->id . ':' . md5($folder),
                            'mapping_id' => $mapping->id,
                            'module' => $mapping->operationalModule,
                            'module_lookup_key' => $this->moduleLookupKeyForRequirement($mapping->operationalModule, $folder),
                            'module_name' => $this->planeModuleNameForRequirement($mapping->operationalModule, $folder),
                            'operational_module_id' => $mapping->operationalModule->id,
                            'source_type' => 'generic',
                            'source_origin' => $mapping->source_origin,
                            'source_folder' => $folder,
                            'source_title' => $mapping->titulo_operativo,
                            'title' => Str::limit($folder . ' · ' . $mapping->titulo_operativo, 240, '...'),
                            'description' => trim((string) $mapping->descripcion_operativa . ' Estudio: ' . $folder . '.'),
                            'requirement_id' => null,
                            'plane_priority' => $mapping->plane_priority ?: 'medium',
                            'responsible_type' => $mapping->responsible_type ?: 'sin_responsable',
                            'operational_cycle_id' => $mapping->operational_cycle_id,
                            'operational_activity_type_id' => $mapping->operational_activity_type_id,
                            'operational_label_ids' => $mapping->operationalLabels->pluck('id')->all(),
                            'planned_start_rule' => $mapping->planned_start_rule ?: 'none',
                            'start_offset_days' => (int) $mapping->start_offset_days,
                            'default_duration_days' => $mapping->default_duration_days,
                            'track_as_kpi' => (bool) $mapping->track_as_kpi,
                            'mapping_created_at' => $mapping->created_at,
                            'has_valid_evidence' => false,
                        ]);
                    })->all();
                }

                return [[
                    'dedupe_key' => 'generic:' . $mapping->id,
                    'mapping_id' => $mapping->id,
                    'module' => $mapping->operationalModule,
                    'module_lookup_key' => (string) $mapping->operationalModule->id,
                    'module_name' => trim($mapping->operationalModule->codigo . ' ' . $mapping->operationalModule->nombre),
                    'operational_module_id' => $mapping->operationalModule->id,
                    'source_type' => 'generic',
                    'source_origin' => $mapping->source_origin,
                    'source_folder' => $mapping->source_folder,
                    'source_title' => $mapping->titulo_operativo,
                    'title' => $mapping->titulo_operativo,
                    'description' => (string) ($mapping->descripcion_operativa ?: 'Gestionar la actividad y dejar soporte listo para validación en Orbit.'),
                    'requirement_id' => null,
                    'plane_priority' => $mapping->plane_priority ?: 'medium',
                    'responsible_type' => $mapping->responsible_type ?: 'sin_responsable',
                    'operational_cycle_id' => $mapping->operational_cycle_id,
                    'operational_activity_type_id' => $mapping->operational_activity_type_id,
                    'operational_label_ids' => $mapping->operationalLabels->pluck('id')->all(),
                    'planned_start_rule' => $mapping->planned_start_rule ?: 'none',
                    'start_offset_days' => (int) $mapping->start_offset_days,
                    'default_duration_days' => $mapping->default_duration_days,
                    'track_as_kpi' => (bool) $mapping->track_as_kpi,
                    'mapping_created_at' => $mapping->created_at,
                    'has_valid_evidence' => false,
                ]];
            });

        return collect()
            ->concat($requirementMappings)
            ->concat($genericMappings)
            ->filter()
            ->map(fn ($blueprint) => $this->withBlueprintDates($project, collect($blueprint), $existingLinks, $requirementActivations, $projectCycles))
            ->values();
    }

    private function withBlueprintDates(Project $project, Collection $blueprint, Collection $existingLinks, Collection $requirementActivations, Collection $projectCycles): Collection
    {
        $existing = $existingLinks->get($blueprint['dedupe_key']);
        if ($existing?->activated_at) {
            $activatedAt = Carbon::parse($existing->activated_at);
        } elseif ($blueprint['requirement_id']) {
            $activatedAt = Carbon::parse($requirementActivations->get($blueprint['requirement_id']) ?: now());
        } else {
            $projectCreatedAt = Carbon::parse($project->created_at ?: $project->fecha_creacion ?: now());
            $mappingCreatedAt = Carbon::parse($blueprint['mapping_created_at'] ?: $projectCreatedAt);
            $activatedAt = $mappingCreatedAt->greaterThan($projectCreatedAt) ? $mappingCreatedAt : $projectCreatedAt;
        }

        $startDate = null;
        if ($blueprint['planned_start_rule'] === 'cycle_start' && $blueprint['operational_cycle_id']) {
            $startDate = optional($projectCycles->get($blueprint['operational_cycle_id']))?->start_date;
        }
        $targetDate = $startDate && $blueprint['default_duration_days']
            ? Carbon::parse($startDate)->addDays(max(1, (int) $blueprint['default_duration_days']) - 1)
            : null;

        return $blueprint->merge([
            'activated_at' => $activatedAt,
            'planned_start_date' => $startDate ? Carbon::parse($startDate)->format('Y-m-d') : null,
            'planned_target_date' => $targetDate?->format('Y-m-d'),
        ]);
    }

    private function createPlaneIssue(
        PendingRequest $request,
        PlaneConnection $connection,
        string $planeProjectId,
        string $title,
        string $description,
        ?string $stateId,
        ?string $planeModuleId,
        string $externalId,
        ?string $priority = null,
        ?array $planeAssigneeIds = null,
        ?string $startDate = null,
        ?string $targetDate = null,
        ?array $planeLabelIds = null
    ): array {
        $variants = $this->issuePayloadVariants($title, $description, $stateId, $planeModuleId, $externalId, true, $priority, $planeAssigneeIds, $startDate, $targetDate, $planeLabelIds);
        $lastResponseBody = null;
        $lastStatus = null;

        foreach ($variants as $payload) {
            $response = $this->planeWriteWithRetry(
                $request,
                'post',
                $this->interpolatedUrl($connection, $connection->issues_path_template, [
                    ...$this->baseReplacements($connection),
                    'project_id' => $planeProjectId,
                ]),
                $payload
            );

            if ($response->successful()) {
                $issueId = $this->extractIssueId($response->json());
                if ($issueId === '') {
                    throw new \RuntimeException('Plane creó la tarea, pero no devolvió un identificador reconocido.');
                }

                return [
                    'id' => $issueId,
                    'body' => $response->json(),
                ];
            }

            $lastStatus = $response->status();
            $lastResponseBody = $response->body();

            if (! in_array($response->status(), [400, 401, 403, 404, 409, 422], true)) {
                break;
            }
        }

        throw new \RuntimeException('No se pudo crear la tarea en Plane. HTTP ' . $lastStatus . ($lastResponseBody ? ' · ' . Str::limit($lastResponseBody, 400) : ''));
    }

    private function updatePlaneIssue(
        PendingRequest $request,
        PlaneConnection $connection,
        string $planeProjectId,
        string $planeIssueId,
        ?string $title,
        ?string $description,
        ?string $stateId,
        ?string $planeModuleId,
        string $externalId,
        ?string $priority = null,
        ?array $planeAssigneeIds = null,
        ?string $startDate = null,
        ?string $targetDate = null,
        ?array $planeLabelIds = null
    ): void {
        $variants = $this->issuePayloadVariants($title ?? 'Actividad', $description ?? '', $stateId, $planeModuleId, $externalId, false, $priority, $planeAssigneeIds, $startDate, $targetDate, $planeLabelIds);
        $variants = collect($variants)
            ->map(function (array $payload) use ($title, $description) {
                if ($title === null) {
                    unset($payload['name']);
                }
                if ($description === null) {
                    unset($payload['description'], $payload['description_html']);
                }

                return $payload;
            })
            ->all();

        $lastResponseBody = null;
        $lastStatus = null;
        foreach ($variants as $payload) {
            $response = $this->planeWriteWithRetry(
                $request,
                'patch',
                $this->interpolatedUrl($connection, $connection->issue_detail_path_template, [
                    ...$this->baseReplacements($connection),
                    'project_id' => $planeProjectId,
                    'issue_id' => $planeIssueId,
                ]),
                $payload
            );

            if ($response->successful()) {
                return;
            }

            $lastStatus = $response->status();
            $lastResponseBody = $response->body();

            if (! in_array($response->status(), [400, 401, 403, 404, 409, 422], true)) {
                break;
            }
        }

        throw new \RuntimeException('No se pudo actualizar la tarea [' . $planeIssueId . '] en Plane. HTTP ' . $lastStatus . ($lastResponseBody ? ' · ' . Str::limit($lastResponseBody, 300) : ''));
    }

    private function assignIssueToModule(
        PendingRequest $request,
        PlaneConnection $connection,
        string $planeProjectId,
        string $planeModuleId,
        string $planeIssueId
    ): void {
        if ($planeModuleId === '' || $planeIssueId === '') {
            return;
        }

        $response = $this->planeWriteWithRetry(
            $request,
            'post',
            $this->interpolatedUrl($connection, $this->moduleIssuesPathTemplate($connection), [
                ...$this->baseReplacements($connection),
                'project_id' => $planeProjectId,
                'module_id' => $planeModuleId,
            ]),
            [
                'issues' => [$planeIssueId],
            ]
        );

        if ($response->successful()) {
            return;
        }

        throw new \RuntimeException('No se pudo asignar la tarea [' . $planeIssueId . '] al módulo [' . $planeModuleId . '] en Plane. HTTP ' . $response->status() . ($response->body() ? ' · ' . Str::limit($response->body(), 300) : ''));
    }

    private function assignIssueToCycle(PendingRequest $request, PlaneConnection $connection, string $planeProjectId, string $planeCycleId, string $planeIssueId): void
    {
        if ($planeCycleId === '' || $planeIssueId === '') {
            return;
        }

        $response = $this->planeWriteWithRetry(
            $request,
            'post',
            $this->interpolatedUrl($connection, $connection->cycle_issues_path_template, [
                ...$this->baseReplacements($connection),
                'project_id' => $planeProjectId,
                'cycle_id' => $planeCycleId,
            ]),
            ['issues' => [$planeIssueId]]
        );

        if (! $response->successful() && $response->status() !== 409) {
            throw new \RuntimeException('No se pudo asignar la tarea [' . $planeIssueId . '] al ciclo [' . $planeCycleId . '] en Plane. HTTP ' . $response->status());
        }
    }

    private function syncProjectSettings(PendingRequest $request, PlaneConnection $connection, string $planeProjectId): void
    {
        $projectUrl = $this->interpolatedUrl($connection, $connection->projects_path . '{project_id}/', [
            ...$this->baseReplacements($connection),
            'project_id' => $planeProjectId,
        ]);

        try {
            $detail = $request->get($projectUrl);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('plane_project_settings_detail_timeout', [
                'plane_project_id' => $planeProjectId,
                'message' => $e->getMessage(),
            ]);

            return;
        }

        if (! $detail->successful()) {
            if (in_array($detail->status(), [404, 429], true)) {
                Log::warning('plane_project_settings_detail_missing', [
                    'plane_project_id' => $planeProjectId,
                    'status' => $detail->status(),
                    'body' => Str::limit($detail->body(), 300),
                ]);

                return;
            }

            throw new \RuntimeException('No se pudo leer el proyecto recién creado en Plane. HTTP ' . $detail->status());
        }

        $current = $detail->json() ?? [];
        $payloads = [];

        if (($current['issue_views_view'] ?? false) !== true) {
            $payloads[] = ['issue_views_view' => true];
        }

        if (($current['module_view'] ?? false) !== true) {
            $payloads[] = ['module_view' => true];
        }

        foreach ($payloads as $payload) {
            $response = $this->planeWriteWithRetry($request, 'patch', $projectUrl, $payload);
            if ($response->successful()) {
                continue;
            }

            if ($response->status() === 400) {
                continue;
            }

            throw new \RuntimeException('No se pudo ajustar la configuración del proyecto en Plane. HTTP ' . $response->status() . ($response->body() ? ' · ' . Str::limit($response->body(), 300) : ''));
        }
    }

    private function issuePayloadVariants(
        string $title,
        string $description,
        ?string $stateId,
        ?string $planeModuleId,
        string $externalId,
        bool $withDescription = true,
        ?string $priority = null,
        ?array $planeAssigneeIds = null,
        ?string $startDate = null,
        ?string $targetDate = null,
        ?array $planeLabelIds = null
    ): array
    {
        $htmlDescription = '<p>' . e($description) . '</p>';
        $priority = $priority && in_array($priority, ['urgent', 'high', 'medium', 'low', 'none'], true) ? $priority : null;
        $planeAssigneeIds = collect($planeAssigneeIds ?? [])->filter()->values()->all();
        $planeLabelIds = collect($planeLabelIds ?? [])->filter()->values()->all();

        $baseVariants = [];
        foreach ([
            ['description_html' => $withDescription ? $htmlDescription : null, 'state' => $stateId, 'module' => $planeModuleId],
            ['description_html' => $withDescription ? $htmlDescription : null, 'state' => $stateId, 'module_ids' => $planeModuleId ? [$planeModuleId] : null],
            ['description_html' => $withDescription ? $htmlDescription : null, 'state_id' => $stateId, 'module_ids' => $planeModuleId ? [$planeModuleId] : null],
            ['description' => $withDescription ? $description : null, 'state' => $stateId, 'module_ids' => $planeModuleId ? [$planeModuleId] : null],
            ['description' => $withDescription ? $description : null, 'state_id' => $stateId, 'module_ids' => $planeModuleId ? [$planeModuleId] : null],
            ['description_html' => $withDescription ? $htmlDescription : null, 'state' => $stateId, 'module_id' => $planeModuleId],
            ['description' => $withDescription ? $description : null, 'state' => $stateId, 'module_id' => $planeModuleId],
        ] as $variant) {
            $baseVariants[] = array_filter([
                'name' => $title,
                ...$variant,
                'priority' => $priority,
                'assignees' => ! empty($planeAssigneeIds) ? $planeAssigneeIds : null,
                'labels' => ! empty($planeLabelIds) ? $planeLabelIds : [],
                'start_date' => $startDate,
                'target_date' => $targetDate,
                'external_source' => 'orbit',
                'external_id' => $externalId,
            ], fn ($value) => $value !== null);
        }

        return array_values(array_unique($baseVariants, SORT_REGULAR));
    }

    private function cyclePayloadVariants(
        OperationalCycle $cycle,
        string $planeProjectId,
        string $startDate,
        string $endDate,
        string $ownerId = ''
    ): array {
        $basePayloads = [
            [
                'name' => $cycle->nombre,
                'description' => (string) ($cycle->descripcion ?: ''),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'owned_by' => $ownerId !== '' ? $ownerId : null,
                'external_source' => 'orbit',
                'external_id' => 'orbit-cycle:' . $cycle->id,
                'timezone' => $cycle->timezone ?: 'America/Bogota',
                'project_id' => $planeProjectId,
            ],
            [
                'name' => $cycle->nombre,
                'description' => (string) ($cycle->descripcion ?: ''),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'owned_by' => $ownerId !== '' ? $ownerId : null,
                'external_source' => 'orbit',
                'external_id' => 'orbit-cycle:' . $cycle->id,
                'timezone' => $cycle->timezone ?: 'America/Bogota',
            ],
            [
                'name' => $cycle->nombre,
                'description' => (string) ($cycle->descripcion ?: ''),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'owned_by' => $ownerId !== '' ? $ownerId : null,
                'external_source' => 'orbit',
                'external_id' => 'orbit-cycle:' . $cycle->id,
            ],
            [
                'name' => $cycle->nombre,
                'description' => (string) ($cycle->descripcion ?: ''),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'external_source' => 'orbit',
                'external_id' => 'orbit-cycle:' . $cycle->id,
            ],
        ];

        return collect($basePayloads)
            ->map(fn (array $payload) => array_filter($payload, fn ($value) => $value !== null && $value !== ''))
            ->unique(fn (array $payload) => md5(json_encode($payload)))
            ->values()
            ->all();
    }

    private function planeProjectMemberDirectory(PendingRequest $request, PlaneConnection $connection, string $planeProjectId): array
    {
        $response = $request->get($this->interpolatedUrl($connection, '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/members/', [
            ...$this->baseReplacements($connection),
            'project_id' => $planeProjectId,
        ]));

        if (! $response->successful()) {
            throw new \RuntimeException('No se pudo listar los miembros del proyecto en Plane. HTTP ' . $response->status());
        }

        $members = [];
        $payload = $response->json('results') ?? $response->json() ?? [];
        foreach ($payload as $item) {
            if (! is_array($item)) {
                continue;
            }

            $member = $item['member'] ?? $item;
            $userId = (string) ($member['id'] ?? $item['id'] ?? '');
            if ($userId !== '') {
                $members[$userId] = true;
            }
        }

        return $members;
    }

    private function ensurePlaneProjectMembers(PendingRequest $request, PlaneConnection $connection, string $planeProjectId, array $planeAssigneeIds, array &$projectMembers): void
    {
        $workspaceMembersById = $this->planeMemberDirectoryById($request, $connection);
        $missing = collect($planeAssigneeIds)
            ->filter(fn ($id) => ! isset($projectMembers[(string) $id]))
            ->values();

        if ($missing->isEmpty()) {
            return;
        }

        foreach ($missing as $id) {
            $response = $this->attachProjectMember(
                $request,
                $connection,
                $planeProjectId,
                (string) $id,
                $workspaceMembersById[(string) $id] ?? null
            );

            $projectMembers[(string) $id] = true;
        }
    }

    private function attachProjectMember(
        PendingRequest $request,
        PlaneConnection $connection,
        string $planeProjectId,
        string $planeUserId,
        ?array $workspaceMember = null
    ): Response {
        $endpoint = $this->interpolatedUrl($connection, '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/members/', [
            ...$this->baseReplacements($connection),
            'project_id' => $planeProjectId,
        ]);

        $lastResponse = null;

        foreach ($this->projectMemberPayloadVariants($planeUserId, $workspaceMember) as $payload) {
            $lastResponse = $this->planeWriteWithRetry($request, 'post', $endpoint, $payload);

            if ($lastResponse->successful()) {
                return $lastResponse;
            }

            if (! in_array($lastResponse->status(), [400, 401, 403, 404, 409, 422], true)) {
                break;
            }
        }

        $body = trim((string) ($lastResponse?->body() ?? ''));

        throw new \RuntimeException(
            'Plane respondió con estado ' . ($lastResponse?->status() ?? 500)
            . ($body !== '' ? ' · ' . Str::limit($body, 300) : '')
        );
    }

    private function planeMemberDirectory(PendingRequest $request, PlaneConnection $connection): array
    {
        $response = $request->get($this->interpolatedUrl($connection, '/api/v1/workspaces/{workspace_slug}/members/', $this->baseReplacements($connection)));
        if (! $response->successful()) {
            throw new \RuntimeException('No se pudo listar los miembros del workspace en Plane. HTTP ' . $response->status());
        }

        $members = [];
        $payload = $response->json('results') ?? $response->json() ?? [];
        foreach ($payload as $item) {
            if (! is_array($item)) {
                continue;
            }
            $member = $item['member'] ?? $item;
            $email = Str::lower(trim((string) ($item['email'] ?? $member['email'] ?? '')));
            $userId = (string) ($member['id'] ?? $item['id'] ?? '');
            if ($email === '' || $userId === '') {
                continue;
            }
            $members[$email] = [
                'plane_user_id' => $userId,
                'email' => $email,
                'display_name' => (string) ($member['display_name'] ?? $item['display_name'] ?? ''),
            ];
        }

        return $members;
    }

    private function planeMemberDirectoryById(PendingRequest $request, PlaneConnection $connection): array
    {
        return collect($this->planeMemberDirectory($request, $connection))
            ->mapWithKeys(fn (array $member) => [
                (string) ($member['plane_user_id'] ?? '') => $member,
            ])
            ->filter(fn (array $member, string $id) => $id !== '')
            ->all();
    }

    private function projectMemberPayloadVariants(string $planeUserId, ?array $workspaceMember = null): array
    {
        $email = trim((string) ($workspaceMember['email'] ?? ''));
        $displayName = trim((string) ($workspaceMember['display_name'] ?? ''));

        $variants = [
            [
                'member' => $planeUserId,
                'role' => 15,
            ],
            [
                'user_id' => $planeUserId,
                'role' => 15,
            ],
            [
                'member_id' => $planeUserId,
                'role' => 15,
            ],
        ];

        if ($email !== '' && $displayName !== '') {
            $variants[] = [
                'email' => $email,
                'display_name' => $displayName,
                'role' => 15,
            ];
            $variants[] = [
                'email' => $email,
                'display_name' => $displayName,
            ];
        }

        if ($email !== '') {
            $variants[] = [
                'email' => $email,
                'display_name' => $displayName !== '' ? $displayName : Str::before($email, '@'),
                'role' => 15,
            ];
        }

        return collect($variants)
            ->map(fn (array $payload) => array_filter($payload, fn ($value) => $value !== null && $value !== ''))
            ->unique(fn (array $payload) => md5(json_encode($payload)))
            ->values()
            ->all();
    }

    private function planeWriteWithRetry(PendingRequest $request, string $method, string $url, array $payload = []): Response
    {
        $delaysMs = [0, 900, 1800, 3500];
        $response = null;

        foreach ($delaysMs as $index => $delayMs) {
            if ($delayMs > 0) {
                usleep($delayMs * 1000);
            }

            $response = match (strtolower($method)) {
                'patch' => $request->patch($url, $payload),
                'put' => $request->put($url, $payload),
                'delete' => $request->delete($url, $payload),
                default => $request->post($url, $payload),
            };

            if ($response->status() !== 429) {
                return $response;
            }
        }

        return $response;
    }

    private function planeIssueDirectories(PendingRequest $request, PlaneConnection $connection, string $planeProjectId): array
    {
        $issues = $this->fetchPlaneIssues($request, $connection, $planeProjectId);
        $issuesByExternalId = [];
        $issuesById = [];
        $issueStatesById = [];

        foreach ($issues as $issue) {
            $issueId = (string) ($issue['id'] ?? '');
            if ($issueId !== '') {
                $issuesById[$issueId] = true;
                $issueStatesById[$issueId] = (string) ($issue['state'] ?? $issue['state_id'] ?? '');
            }

            $externalId = (string) ($issue['external_id'] ?? '');
            if ($externalId === '') {
                continue;
            }

            if (! isset($issuesByExternalId[$externalId])) {
                $issuesByExternalId[$externalId] = [
                    'id' => $issueId,
                    'duplicate_count' => 1,
                ];
                continue;
            }

            $issuesByExternalId[$externalId]['duplicate_count']++;
        }

        return [$issuesByExternalId, $issuesById, $issueStatesById];
    }

    private function desiredPlaneStateCode(Collection|array $blueprint): ?string
    {
        $blueprint = $blueprint instanceof Collection ? $blueprint->all() : $blueprint;

        if (($blueprint['source_type'] ?? null) !== 'requirement') {
            return null;
        }

        return ! empty($blueprint['has_valid_evidence']) ? 'completado' : 'pendiente';
    }

    private function desiredPlaneStateId(?string $desiredStateCode, ?string $pendingStateId, ?string $completedStateId): ?string
    {
        return match ($desiredStateCode) {
            'completado' => $completedStateId,
            'pendiente' => $pendingStateId,
            default => null,
        };
    }

    private function planeStateCodeDirectory(Collection $states): array
    {
        $directory = [];

        foreach (['pendiente', 'en_ejecucion', 'en_revision', 'ajustes', 'completado', 'bloqueado'] as $code) {
            $stateId = $this->resolvePlaneStateId($states, $code);
            if ($stateId) {
                $directory[(string) $stateId] = $code;
            }
        }

        return $directory;
    }

    private function stateCodeByIssueId(array $issueStatesById, array $stateCodeById, string $issueId, ?string $fallback = null): ?string
    {
        $stateId = (string) ($issueStatesById[$issueId] ?? '');

        return $stateCodeById[$stateId] ?? $fallback;
    }

    private function syncRequirementEvidenceStateEvents(PlaneTaskLink $link, ?string $desiredStateCode, ?string $previousStateCode): void
    {
        if ($link->source_type !== 'requirement' || $desiredStateCode === null || $desiredStateCode === $previousStateCode) {
            return;
        }

        if ($desiredStateCode === 'completado') {
            $this->recordActivityEvent($link, 'requirement_auto_completed', now(), [
                'reason' => 'valid_evidence_detected',
            ]);

            return;
        }

        if ($desiredStateCode === 'pendiente') {
            $this->recordActivityEvent($link, 'requirement_reopened_missing_evidence', now(), [
                'reason' => 'valid_evidence_missing',
            ]);
        }
    }

    private function assertGenericActivitiesSynced(Project $project, Collection $blueprints, array $issuesByExternalId): void
    {
        $expectedGenericKeys = $blueprints
            ->where('source_type', 'generic')
            ->pluck('dedupe_key')
            ->values();

        if ($expectedGenericKeys->isEmpty()) {
            return;
        }

        $linksByKey = $project->planeTaskLinks()
            ->where('source_type', 'generic')
            ->get()
            ->keyBy('dedupe_key');

        $missing = $expectedGenericKeys->filter(function (string $dedupeKey) use ($linksByKey, $issuesByExternalId) {
            $link = $linksByKey->get($dedupeKey);

            if ($link && filled($link->plane_issue_id) && $link->status === 'active') {
                return false;
            }

            return ! isset($issuesByExternalId[$dedupeKey]);
        })->values();

        if ($missing->isEmpty()) {
            return;
        }

        $titlesByKey = $blueprints
            ->where('source_type', 'generic')
            ->mapWithKeys(fn ($item) => [(string) $item['dedupe_key'] => (string) $item['title']])
            ->all();

        $labels = $missing
            ->map(fn (string $key) => $titlesByKey[$key] ?? $key)
            ->implode(', ');

        throw new \RuntimeException('Faltaron actividades base esperadas en Plane: ' . $labels . '.');
    }

    private function fetchPlaneIssues(PendingRequest $request, PlaneConnection $connection, string $planeProjectId): array
    {
        $issues = [];
        $cursor = null;
        $seenCursors = [];

        do {
            $response = $request->get(
                $this->interpolatedUrl($connection, $connection->issues_path_template ?: '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/issues/', [
                    ...$this->baseReplacements($connection),
                    'project_id' => $planeProjectId,
                ]),
                array_filter(['cursor' => $cursor])
            );

            if (! $response->successful()) {
                throw new \RuntimeException('No se pudo listar las tareas actuales del proyecto en Plane. HTTP ' . $response->status());
            }

            foreach (($response->json('results') ?? []) as $issue) {
                if (is_array($issue) && filled($issue['id'] ?? null)) {
                    $issues[] = $issue;
                }
            }

            $nextCursor = $response->json('next_cursor');
            $hasMore = (bool) $response->json('next_page_results');

            if (! $hasMore || empty($nextCursor) || isset($seenCursors[$nextCursor])) {
                $cursor = null;
                continue;
            }

            $seenCursors[$nextCursor] = true;
            $cursor = $nextCursor;
        } while ($cursor !== null);

        return $issues;
    }

    private function resolveTaskAssignee(Project $project, Collection|array $blueprint, array $memberDirectory): array
    {
        $blueprint = $blueprint instanceof Collection ? $blueprint->all() : $blueprint;
        $responsibleType = (string) ($blueprint['responsible_type'] ?? 'sin_responsable');
        $resolvedEntity = match ($responsibleType) {
            'formulador' => $project->formulador,
            'estructurador' => $project->estructurador,
            'apoyo_ambiental' => $this->environmentalSupportContact($project),
            'especialista_estudio' => $this->studyAssignedSpecialist($project, (string) ($blueprint['source_folder'] ?? '')),
            default => null,
        };

        $email = $this->entityEmail($resolvedEntity);
        $planeAssignee = $email !== '' ? ($memberDirectory[$email] ?? null) : null;
        $note = null;

        if ($resolvedEntity instanceof Specialist) {
            $this->syncSpecialistPlaneReference($resolvedEntity, $planeAssignee, $email);
        }

        if (! $planeAssignee && $responsibleType !== 'sin_responsable') {
            $fallbackUser = $project->estructurador;
            $fallbackEmail = Str::lower(trim((string) ($fallbackUser?->email ?? '')));
            $fallbackPlane = $fallbackEmail !== '' ? ($memberDirectory[$fallbackEmail] ?? null) : null;
            if ($fallbackPlane) {
                $note = 'No se encontró el responsable esperado en Plane. Se asignó al estructurador como respaldo.';
                return [
                    'resolved_user_id' => $fallbackUser?->id,
                    'resolved_user_email' => $fallbackEmail ?: null,
                    'resolved_plane_assignee_id' => $fallbackPlane['plane_user_id'],
                    'plane_assignee_ids' => [$fallbackPlane['plane_user_id']],
                    'assignment_note' => $note,
                ];
            }

            $note = 'No se encontró el responsable esperado en Plane y tampoco fue posible usar al estructurador como respaldo.';
        }

        return [
            'resolved_user_id' => $resolvedEntity instanceof \App\Models\User ? $resolvedEntity->id : null,
            'resolved_user_email' => $email ?: null,
            'resolved_plane_assignee_id' => $planeAssignee['plane_user_id'] ?? null,
            'plane_assignee_ids' => isset($planeAssignee['plane_user_id']) ? [$planeAssignee['plane_user_id']] : null,
            'assignment_note' => $note,
        ];
    }

    private function projectTeamPlaneAssigneeIds(Project $project, array $memberDirectory): array
    {
        $relations = ['formulador', 'estructurador', 'profesionalAmbiental'];
        if (Schema::hasTable('project_study_specialist_assignments')) {
            if (Schema::hasTable('specialists')) {
                $relations[] = 'studySpecialistAssignments.specialist';
            }
            $relations[] = 'studySpecialistAssignments.user';
        }

        $project->loadMissing($relations);

        $entities = collect([
            $project->formulador,
            $project->estructurador,
            $this->environmentalSupportContact($project),
        ]);

        if ($project->relationLoaded('studySpecialistAssignments')) {
            $studyEntities = $project->studySpecialistAssignments
                ->map(fn ($assignment) => $assignment->specialist ?: $assignment->user);

            $entities = $entities->concat($studyEntities);
        }

        return $entities
            ->filter()
            ->unique(function ($entity) {
                return get_class($entity) . ':' . ($entity->getKey() ?? spl_object_id($entity));
            })
            ->map(function ($entity) use ($memberDirectory) {
                $email = $this->entityEmail($entity);
                $planeAssignee = $email !== '' ? ($memberDirectory[$email] ?? null) : null;

                if ($entity instanceof Specialist) {
                    $this->syncSpecialistPlaneReference($entity, $planeAssignee, $email);
                }

                return $planeAssignee['plane_user_id'] ?? null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function studyAssignedSpecialist(Project $project, string $folder): Specialist|\App\Models\User|null
    {
        $assignment = $project->studySpecialistAssignments
            ->first(function ($row) use ($folder) {
                $rowFolder = (string) ($row->study_folder ?? '');

                if ($rowFolder === $folder) {
                    return true;
                }

                return $this->normalizeStudyFolder($rowFolder) === $this->normalizeStudyFolder($folder);
            });

        return $assignment?->specialist ?: $assignment?->user;
    }

    private function normalizeStudyFolder(string $value): string
    {
        $value = trim(Str::lower(Str::ascii($value)));

        return preg_replace('/\s+/', ' ', $value) ?: '';
    }

    private function environmentalSupportContact(Project $project): ?ProfesionalAmbiental
    {
        $contact = $project->profesionalAmbiental;
        $email = Str::lower(trim((string) ($contact?->correo ?? '')));

        return $email !== '' ? $contact : null;
    }

    private function entityEmail(mixed $entity): string
    {
        return Str::lower(trim((string) ($entity->email ?? $entity->correo ?? '')));
    }

    private function syncUserPlaneReference(User $user, ?array $planeMember, ?string $message, string $status): void
    {
        $user->forceFill([
            'plane_user_id' => $planeMember['plane_user_id'] ?? ($status === 'linked' ? $user->plane_user_id : null),
            'plane_sync_status' => $status,
            'plane_last_error' => $message,
            'plane_last_synced_at' => now(),
        ])->save();
    }

    private function syncSpecialistPlaneReference(
        Specialist $specialist,
        ?array $planeAssignee,
        string $email,
        ?string $customMessage = null,
        ?string $forcedStatus = null
    ): void
    {
        if ($email === '') {
            $specialist->forceFill([
                'plane_sync_status' => $forcedStatus ?: 'error',
                'plane_last_error' => $customMessage ?: 'El especialista no tiene correo válido para buscarlo en Plane.',
                'plane_last_synced_at' => now(),
            ])->save();
            return;
        }

        if ($planeAssignee) {
            $specialist->forceFill([
                'plane_user_id' => $planeAssignee['plane_user_id'],
                'plane_sync_status' => $forcedStatus ?: 'linked',
                'plane_last_error' => $customMessage,
                'plane_last_synced_at' => now(),
            ])->save();
            return;
        }

        $specialist->forceFill([
            'plane_sync_status' => $forcedStatus ?: 'not_found',
            'plane_last_error' => $customMessage ?: 'No se encontró el especialista como miembro activo del workspace en Plane.',
            'plane_last_synced_at' => now(),
        ])->save();
    }

    private function fetchPlaneStates(PendingRequest $request, PlaneConnection $connection, string $planeProjectId): array
    {
        $response = $request->get(
            $this->interpolatedUrl($connection, $connection->states_path_template, [
                ...$this->baseReplacements($connection),
                'project_id' => $planeProjectId,
            ])
        );

        if (! $response->successful()) {
            throw new \RuntimeException('No se pudo listar los estados actuales del proyecto en Plane. HTTP ' . $response->status());
        }

        return collect($response->json('results', []))
            ->filter(fn ($item) => is_array($item) && filled($item['id'] ?? null))
            ->sortBy(fn (array $item) => [(int) ($item['sequence'] ?? 0), (string) ($item['name'] ?? '')])
            ->values()
            ->all();
    }

    private function fetchPlaneModules(PendingRequest $request, PlaneConnection $connection, string $planeProjectId): array
    {
        $response = $request->get(
            $this->interpolatedUrl($connection, $connection->modules_path_template, [
                ...$this->baseReplacements($connection),
                'project_id' => $planeProjectId,
            ])
        );

        if (! $response->successful()) {
            throw new \RuntimeException('No se pudo listar los módulos actuales del proyecto en Plane. HTTP ' . $response->status());
        }

        return collect($response->json('results', []))
            ->filter(fn ($item) => is_array($item) && filled($item['id'] ?? null))
            ->values()
            ->all();
    }

    private function fetchPlaneCatalog(PendingRequest $request, PlaneConnection $connection, string $path, string $planeProjectId, string $label): array
    {
        $response = $request->get($this->interpolatedUrl($connection, $path, [
            ...$this->baseReplacements($connection), 'project_id' => $planeProjectId,
        ]));
        if (! $response->successful()) {
            throw new \RuntimeException('No se pudieron listar ' . $label . ' en Plane. HTTP ' . $response->status());
        }

        $payload = $response->json('results');
        if (! is_array($payload)) {
            $payload = $response->json();
        }

        return collect(is_array($payload) ? $payload : [])
            ->filter(fn ($item) => is_array($item) && filled($item['id'] ?? null))
            ->values()
            ->all();
    }

    private function resolveCycleDates(Project $project, OperationalCycle $cycle): array
    {
        if ($cycle->anchor_type === 'fixed_date') {
            if (! $cycle->fixed_start_date || ! $cycle->fixed_end_date) {
                throw new \RuntimeException('El ciclo [' . $cycle->nombre . '] requiere fecha inicial y final.');
            }

            return [$cycle->fixed_start_date->format('Y-m-d'), $cycle->fixed_end_date->format('Y-m-d')];
        }

        $anchor = Carbon::parse($project->created_at ?: $project->fecha_creacion ?: now())->startOfDay();
        $start = $anchor->copy()->addDays((int) $cycle->start_offset_days);
        $end = $start->copy()->addDays(max(1, (int) $cycle->duration_days) - 1);

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    private function updatePlaneState(PendingRequest $request, PlaneConnection $connection, string $planeProjectId, string $planeStateId, array $payload, string $label): void
    {
        $detailTemplate = rtrim($connection->states_path_template, '/') . '/{state_id}/';
        $response = $this->planeWriteWithRetry(
            $request,
            'patch',
            $this->interpolatedUrl($connection, $detailTemplate, [
                ...$this->baseReplacements($connection),
                'project_id' => $planeProjectId,
                'state_id' => $planeStateId,
            ]),
            $payload
        );

        if (! $response->successful()) {
            $this->throwPlaneStateHttpError('actualizar', $planeProjectId, $label, $response);
        }
    }

    private function deletePlaneState(PendingRequest $request, PlaneConnection $connection, string $planeProjectId, string $planeStateId, string $label): void
    {
        $detailTemplate = rtrim($connection->states_path_template, '/') . '/{state_id}/';
        $response = $this->planeWriteWithRetry(
            $request,
            'delete',
            $this->interpolatedUrl($connection, $detailTemplate, [
                ...$this->baseReplacements($connection),
                'project_id' => $planeProjectId,
                'state_id' => $planeStateId,
            ])
        );

        if (! $response->successful()) {
            $this->throwPlaneStateHttpError('eliminar', $planeProjectId, $label, $response);
        }
    }

    private function throwPlaneStateHttpError(string $action, string $planeProjectId, string $label, Response $response): never
    {
        $body = trim((string) $response->body());
        Log::error('plane_state_sync_failed', [
            'action' => $action,
            'plane_project_id' => $planeProjectId,
            'state' => $label,
            'http_status' => $response->status(),
            'response_body' => Str::limit($body, 1000),
        ]);

        throw new \RuntimeException(
            'No se pudo ' . $action . ' el estado [' . $label . '] en Plane. HTTP ' . $response->status()
            . ($body !== '' ? ' · ' . Str::limit($body, 300) : '')
        );
    }

    private function updatePlaneModule(PendingRequest $request, PlaneConnection $connection, string $planeProjectId, string $planeModuleId, array $payload, string $label): void
    {
        $detailTemplate = rtrim($connection->modules_path_template, '/') . '/{module_id}/';
        $response = $request->patch(
            $this->interpolatedUrl($connection, $detailTemplate, [
                ...$this->baseReplacements($connection),
                'project_id' => $planeProjectId,
                'module_id' => $planeModuleId,
            ]),
            $payload
        );

        if (! $response->successful()) {
            throw new \RuntimeException('No se pudo actualizar el módulo [' . $label . '] en Plane. HTTP ' . $response->status());
        }
    }

    private function statePayload(OperationalState $state): array
    {
        return [
            'name' => $state->nombre,
            'description' => 'Estado operativo Orbit: ' . $state->codigo,
            'color' => $state->color ?: '#9CA3AF',
            'sequence' => $state->orden * 10000,
            'group' => $this->mapPlaneStateGroup($state),
            'default' => $state->codigo === 'pendiente',
            'external_id' => (string) $state->id,
            'external_source' => 'orbit',
        ];
    }

    private function findOrbitPlaneState(Collection $states, OperationalState $state): ?array
    {
        return $states->first(function (array $planeState) use ($state): bool {
            return (string) ($planeState['external_source'] ?? '') === 'orbit'
                && (string) ($planeState['external_id'] ?? '') === (string) $state->id;
        });
    }

    private function isNativePlaneBootstrapState(array $state): bool
    {
        if (filled($state['external_source'] ?? null) || filled($state['external_id'] ?? null)) {
            return false;
        }

        $nativeStates = [
            'Backlog' => 'backlog',
            'Todo' => 'unstarted',
            'In Progress' => 'started',
            'Done' => 'completed',
            'Cancelled' => 'cancelled',
        ];
        $name = (string) ($state['name'] ?? '');

        return isset($nativeStates[$name]) && (string) ($state['group'] ?? '') === $nativeStates[$name];
    }

    private function hasCompleteOrbitStateSeed(Collection $states): bool
    {
        $catalog = OperationalState::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();

        if ($catalog->isEmpty()) {
            return false;
        }

        if ($states->contains(fn (array $state): bool => $this->isNativePlaneBootstrapState($state))) {
            return false;
        }

        foreach ($catalog as $state) {
            $matched = $this->findOrbitPlaneState($states, $state);

            if (! $matched) {
                $matched = $states->first(function (array $planeState) use ($state): bool {
                    return (string) ($planeState['name'] ?? '') === (string) $state->nombre;
                });
            }

            if (! $matched) {
                return false;
            }
        }

        return true;
    }

    private function modulePayload(array $module): array
    {
        return [
            'name' => $module['name'],
            'description' => $module['description'],
            'external_id' => (string) $module['external_id'],
            'external_source' => 'orbit',
        ];
    }

    private function projectPayload(Project $project): array
    {
        return [
            'name' => (string) ($project->nombre_clave ?: $project->nombre ?: ('Proyecto ' . $project->id)),
            'description' => (string) ($project->objeto_proyecto ?: ''),
            'identifier' => $this->buildPlaneIdentifier($project),
            'external_source' => 'orbit',
            'external_id' => $this->planeExternalProjectId($project),
            'module_view' => true,
            'cycle_view' => true,
            'issue_views_view' => true,
            'page_view' => true,
        ];
    }

    private function buildPlaneIdentifier(Project $project): string
    {
        $raw = (string) ($project->id_proyecto ?: $project->nombre_clave ?: ('PRJ-' . $project->id));
        $identifier = Str::of($raw)
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->value();
        $suffix = strtoupper(base_convert((string) max(1, (int) $project->id), 10, 36));

        if ($identifier === '') {
            $identifier = 'PRJ';
        }

        return substr($identifier, 0, max(1, 12 - strlen($suffix))) . $suffix;
    }

    private function planeExternalProjectId(Project $project): string
    {
        return 'orbit-project:' . $project->id;
    }

    private function mapPlaneStateGroup(OperationalState $state): string
    {
        return match ($state->codigo) {
            'pendiente' => 'backlog',
            'en_ejecucion' => 'started',
            'en_revision' => 'started',
            'ajustes' => 'unstarted',
            'completado' => 'completed',
            'bloqueado' => 'cancelled',
            default => $state->es_final ? 'completed' : 'backlog',
        };
    }

    private function resolvePlaneStateId(Collection $states, string $code): ?string
    {
        $configuredState = OperationalState::query()->where('codigo', $code)->first();
        if ($configuredState) {
            $orbitState = $this->findOrbitPlaneState($states, $configuredState);
            if ($orbitState) {
                return (string) $orbitState['id'];
            }
        }

        $group = match ($code) {
            'pendiente' => 'backlog',
            'en_ejecucion', 'en_revision' => 'started',
            'ajustes' => 'unstarted',
            'completado' => 'completed',
            'bloqueado' => 'cancelled',
            default => 'backlog',
        };

        $exact = $states->first(function (array $state) use ($group): bool {
            return (string) ($state['group'] ?? '') === $group
                && (bool) ($state['external_source'] ?? false) === false;
        });

        if ($exact) {
            return (string) $exact['id'];
        }

        return $states->firstWhere('group', $group)['id'] ?? null;
    }

    private function resolveDefaultProjectStateId(PendingRequest $request, PlaneConnection $connection, string $planeProjectId): ?string
    {
        $states = collect($this->fetchPlaneStates($request, $connection, $planeProjectId));

        $default = $states->firstWhere('default', true);
        if ($default) {
            return (string) ($default['id'] ?? '');
        }

        $backlog = $states->firstWhere('group', 'backlog');

        return $backlog ? (string) ($backlog['id'] ?? '') : null;
    }

    private function planeProjectExists(
        PendingRequest $request,
        PlaneConnection $connection,
        string $planeProjectId,
        ?Project $project = null
    ): bool
    {
        try {
            $response = $request->get(
                $this->interpolatedUrl($connection, $connection->projects_path . '{project_id}/', [
                    ...$this->baseReplacements($connection),
                    'project_id' => $planeProjectId,
                ])
            );
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('plane_project_exists_detail_timeout', [
                'plane_project_id' => $planeProjectId,
                'message' => $e->getMessage(),
            ]);

            return $this->planeProjectExistsViaList($request, $connection, $planeProjectId, $project);
        }

        if ($response->successful()) {
            return true;
        }

        if ($response->status() === 404) {
            return $this->planeProjectExistsViaList($request, $connection, $planeProjectId, $project);
        }

        throw new \RuntimeException('No se pudo validar la existencia del proyecto en Plane. HTTP ' . $response->status());
    }

    private function planeProjectExistsViaList(
        PendingRequest $request,
        PlaneConnection $connection,
        string $planeProjectId,
        ?Project $project = null
    ): bool
    {
        $results = $this->listPlaneProjects($request, $connection);

        if (collect($results)->contains(fn (array $item): bool => (string) ($item['id'] ?? '') === $planeProjectId)) {
            return true;
        }

        if ($project) {
            return $this->findExistingPlaneProjectInResults($results, $project) !== null;
        }

        return false;
    }

    private function findExistingPlaneProject(PendingRequest $request, PlaneConnection $connection, Project $project): ?array
    {
        $results = $this->listPlaneProjects($request, $connection);

        return $this->findExistingPlaneProjectInResults($results, $project);
    }

    private function listPlaneProjects(PendingRequest $request, PlaneConnection $connection): array
    {
        $results = [];
        $cursor = null;
        $seenCursors = [];

        do {
            $response = $request->get(
                $this->interpolatedUrl($connection, $connection->projects_path, $this->baseReplacements($connection)),
                array_filter(['cursor' => $cursor])
            );

            if (! $response->successful()) {
                throw new \RuntimeException('No se pudo consultar los proyectos existentes en Plane. HTTP ' . $response->status());
            }

            foreach (($response->json('results') ?? []) as $item) {
                if (is_array($item)) {
                    $results[] = $item;
                }
            }

            $nextCursor = $response->json('next_cursor');
            $hasMore = (bool) $response->json('next_page_results');

            if (! $hasMore || empty($nextCursor) || isset($seenCursors[$nextCursor])) {
                $cursor = null;
                continue;
            }

            $seenCursors[$nextCursor] = true;
            $cursor = $nextCursor;
        } while ($cursor !== null);

        return $results;
    }

    private function findExistingPlaneProjectInResults(array $results, Project $project): ?array
    {
        $externalId = $this->planeExternalProjectId($project);
        $identifier = $this->buildPlaneIdentifier($project);
        $projectName = trim((string) ($project->nombre_clave ?: $project->nombre ?: ('Proyecto ' . $project->id)));

        return collect($results)->first(function (array $item) use ($externalId, $identifier, $projectName): bool {
            $itemExternalId = (string) ($item['external_id'] ?? '');
            $itemExternalSource = (string) ($item['external_source'] ?? '');
            $itemIdentifier = Str::upper(trim((string) ($item['identifier'] ?? '')));
            $itemName = trim((string) ($item['name'] ?? ''));

            return ($itemExternalSource === 'orbit' && $itemExternalId !== '' && $itemExternalId === $externalId)
                || ($itemIdentifier !== '' && $itemIdentifier === $identifier)
                || ($itemName !== '' && $itemName === $projectName);
        });
    }

    private function resetPlaneTaskLinks(Project $project): void
    {
        $project->planeTaskLinks()->update([
            'plane_project_id' => null,
            'plane_issue_id' => null,
            'plane_module_id' => null,
            'sync_error' => 'El proyecto previo de Plane ya no existía y Orbit lo recreará.',
            'last_synced_at' => now(),
        ]);
    }

    private function planeModuleDefinitions(Project $project): Collection
    {
        $baseModules = OperationalModule::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->get()
            ->map(fn (OperationalModule $module) => [
                'external_id' => (string) $module->id,
                'name' => trim($module->codigo . ' ' . $module->nombre),
                'description' => $module->descripcion ?: ('Módulo operativo Orbit ' . $module->codigo),
                'operational_module_id' => $module->id,
                'source_folder' => null,
            ]);

        $studyModules = $this->mappingService
            ->applicableStudyFolders($project)
            ->values()
            ->map(function (string $folder) {
                return [
                    'external_id' => $this->studyModuleExternalId($folder),
                    'name' => '02 · ' . $folder,
                    'description' => 'Frente operativo específico para ' . $folder . '.',
                    'operational_module_id' => OperationalModule::query()->where('codigo', '02')->value('id'),
                    'source_folder' => $folder,
                ];
            });

        return $baseModules
            ->concat($studyModules)
            ->values();
    }

    private function moduleLookupKeyForRequirement(OperationalModule $module, ?string $folder): string
    {
        if ($module->codigo === '02' && filled($folder)) {
            return $this->studyModuleExternalId((string) $folder);
        }

        return (string) $module->id;
    }

    private function planeModuleNameForRequirement(OperationalModule $module, ?string $folder): string
    {
        if ($module->codigo === '02' && filled($folder)) {
            return '02 · ' . $folder;
        }

        return trim($module->codigo . ' ' . $module->nombre);
    }

    private function studyModuleExternalId(string $folder): string
    {
        return 'study:' . Str::slug(Str::ascii($folder), '-') . ':' . substr(md5(Str::lower($folder)), 0, 8);
    }

    private function discardedTitle(string $title): string
    {
        return Str::startsWith($title, '[Descartada] ')
            ? $title
            : '[Descartada] ' . $title;
    }

    private function baseReplacements(PlaneConnection $connection): array
    {
        return [
            'workspace_slug' => (string) $connection->workspace_id,
        ];
    }

    private function authorizedRequest(PlaneConnection $connection): PendingRequest
    {
        $request = Http::timeout(max(1, (int) $connection->timeout_segundos))
            ->acceptJson()
            ->asJson()
            ->retry([1000, 2000, 5000], function (\Exception $exception, PendingRequest $request) {
                if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                    return true;
                }

                return false;
            }, throw: false);

        return match ($connection->auth_type) {
            'api_key' => $request->withHeaders(array_filter([
                $connection->api_key_header ?: 'X-API-Key' => $connection->api_key,
                $connection->api_secret_header ?: 'X-API-Secret' => $connection->api_secret,
            ], fn ($value) => filled($value))),
            'oauth_client_credentials' => $request->withToken($this->oauthAccessToken($connection)),
            default => $request->withToken((string) $connection->access_token),
        };
    }

    private function oauthAccessToken(PlaneConnection $connection): string
    {
        if (blank($connection->oauth_token_url)) {
            throw new \RuntimeException('Falta la URL de token OAuth para Plane.');
        }

        $response = Http::timeout(max(1, (int) $connection->timeout_segundos))
            ->asForm()
            ->post($this->fullUrl($connection, $connection->oauth_token_url), [
                'grant_type' => 'client_credentials',
                'client_id' => (string) $connection->client_id,
                'client_secret' => (string) $connection->client_secret,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('No se pudo obtener token OAuth de Plane. HTTP ' . $response->status());
        }

        $token = Arr::get($response->json(), 'access_token');
        if (! is_string($token) || $token === '') {
            throw new \RuntimeException('Plane no devolvió access_token en la autenticación OAuth.');
        }

        return $token;
    }

    private function fullUrl(PlaneConnection $connection, ?string $path): string
    {
        $base = rtrim((string) $connection->url_base, '/');
        $path = (string) ($path ?? '');
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        return $base . '/' . ltrim($path, '/');
    }

    private function interpolatedUrl(PlaneConnection $connection, string $template, array $replacements): string
    {
        $path = strtr($template, collect($replacements)->mapWithKeys(fn ($value, $key) => ['{' . $key . '}' => (string) $value])->all());

        return $this->fullUrl($connection, $path);
    }

    private function normalizedProjectUrlTemplate(?string $template): string
    {
        $template = trim((string) ($template ?? ''));

        if ($template === '') {
            return '/{workspace_slug}/projects/{project_id}/issues/';
        }

        if (Str::startsWith($template, ['http://', 'https://'])) {
            return $template;
        }

        $template = '/' . ltrim($template, '/');

        if (! Str::contains($template, '{workspace_slug}')) {
            if (Str::startsWith($template, '/projects/')) {
                $template = '/{workspace_slug}' . $template;
            } else {
                $template = '/{workspace_slug}/' . ltrim($template, '/');
            }
        }

        if (preg_match('#/\{project_id\}/?$#', $template) === 1 && ! Str::contains($template, '/issues')) {
            $template = rtrim($template, '/') . '/issues/';
        }

        $template = preg_replace('#\s+/#', '/', $template) ?? $template;
        $template = preg_replace('#/\s+#', '/', $template) ?? $template;

        return $template;
    }

    private function moduleIssuesPathTemplate(PlaneConnection $connection): string
    {
        return rtrim($connection->modules_path_template, '/') . '/{module_id}/module-issues/';
    }

    private function extractProjectId(array $payload): ?string
    {
        $candidates = [
            Arr::get($payload, 'id'),
            Arr::get($payload, 'project.id'),
            Arr::get($payload, 'data.id'),
            Arr::get($payload, 'data.project.id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }

    private function extractIssueId(array $payload): string
    {
        $candidates = [
            Arr::get($payload, 'id'),
            Arr::get($payload, 'issue.id'),
            Arr::get($payload, 'data.id'),
            Arr::get($payload, 'data.issue.id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_scalar($candidate) && (string) $candidate !== '') {
                return (string) $candidate;
            }
        }

        return '';
    }

    private function extractProjectUrl(array $payload, PlaneConnection $connection, string $projectId): string
    {
        $candidates = [
            Arr::get($payload, 'url'),
            Arr::get($payload, 'html_url'),
            Arr::get($payload, 'link'),
            Arr::get($payload, 'project.url'),
            Arr::get($payload, 'data.url'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return $this->interpolatedUrl($connection, $this->normalizedProjectUrlTemplate($connection->project_url_template), [
            ...$this->baseReplacements($connection),
            'project_id' => $projectId,
        ]);
    }
}
