<?php

namespace App\Http\Controllers;

use App\Jobs\ProvisionPlaneProjectJob;
use App\Models\Project;
use App\Models\ProjectStudySpecialistAssignment;
use App\Models\Requirement;
use App\Models\Specialist;
use App\Services\OperationalActivityMappingService;
use App\Services\PlaneProvisioningService;
use App\Services\ProjectStatusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ChecklistController extends Controller
{
    public function __construct(
        private readonly OperationalActivityMappingService $mappingService,
    ) {
    }

    public function show(Project $project)
    {
        $relations = ['sectores'];
        if (Schema::hasTable('project_study_specialist_assignments') && Schema::hasTable('specialists')) {
            if (Schema::hasTable('specialists')) {
                $relations[] = 'studySpecialistAssignments.specialist';
            }
            $relations[] = 'studySpecialistAssignments.user';
        }
        $project->load($relations);
        $sectorCatalog = $this->projectSectorCatalog($project);

        $requirements = Requirement::query()
            ->where('visible', true)
            ->where(function ($query) use ($project) {
                $query->whereNull('custom_project_id')
                    ->orWhere('custom_project_id', $project->id);
            })
            ->orderBy('carpeta')
            ->orderByRaw('custom_project_id IS NOT NULL')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->get();

        $requirements = $this->filterSectorial($requirements, $sectorCatalog['names']);

        $applied = $project->requisitos()->pluck('requirements.id')->all();
        $studyFolders = $this->mappingService->applicableStudyFolders($project)->all();
        $studyAssignments = Schema::hasTable('project_study_specialist_assignments')
            ? $project->studySpecialistAssignments
                ->mapWithKeys(fn ($assignment) => [$assignment->study_folder => $assignment->specialist_id ? (int) $assignment->specialist_id : ($assignment->user_id ? (int) $assignment->user_id : null)])
                ->all()
            : [];

        $specialistOptions = Schema::hasTable('specialists')
            ? Specialist::query()
                ->where('activo', true)
                ->whereNotNull('correo')
                ->where('correo', '!=', '')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'correo', 'especialidad'])
                ->mapWithKeys(fn (Specialist $specialist) => [$specialist->id => trim($specialist->nombre . ($specialist->especialidad ? ' · ' . $specialist->especialidad : '') . ' · ' . $specialist->correo)])
                ->all()
            : [];

        $specialistDetails = Schema::hasTable('specialists')
            ? Specialist::query()
                ->whereIn('id', array_filter(array_values($studyAssignments)))
                ->get(['id', 'nombre', 'correo', 'plane_sync_status', 'plane_last_error', 'plane_user_id'])
                ->mapWithKeys(fn (Specialist $specialist) => [$specialist->id => [
                    'id' => (int) $specialist->id,
                    'nombre' => (string) $specialist->nombre,
                    'correo' => (string) $specialist->correo,
                    'plane_sync_status' => (string) ($specialist->plane_sync_status ?: 'pending'),
                    'plane_last_error' => (string) ($specialist->plane_last_error ?: ''),
                    'plane_user_id' => (string) ($specialist->plane_user_id ?: ''),
                ]])
                ->all()
            : [];

        $totalsByFolder = $requirements
            ->groupBy(function ($req) {
                return $req->carpeta ?? 'Sin carpeta';
            })
            ->map(function ($items) use ($applied) {
                $totalCheckable = $items->filter(function ($req) {
                    return strtoupper((string) $req->requiere_check) === 'SI';
                })->count();
                $active = $items->filter(function ($req) use ($applied) {
                    return strtoupper((string) $req->requiere_check) === 'SI'
                        && in_array($req->id, $applied, true);
                })->count();
                return [
                    'total' => $totalCheckable,
                    'active' => $active,
                ];
            });

        $grouped = $requirements
            ->groupBy(function ($req) {
                return $req->carpeta ?? 'Sin carpeta';
            })
            ->map(function ($items, $folderName) {
                $isSectorialFolder = str_contains($this->normalizeSector((string) $folderName), 'documentos sectoriales');
                if (! $isSectorialFolder) {
                    return $this->buildRequirementGroups($items);
                }

                $groups = [];
                $itemsBySector = $items->groupBy(function ($req) {
                    return $this->normalizeSector($req->sector) ?: 'sin-sector';
                });
                foreach ($itemsBySector as $sectorItems) {
                    foreach ($this->buildRequirementGroups($sectorItems) as $group) {
                        $groups[] = $group;
                    }
                }

                return $groups;
            });

        return view('checklist.show', [
            'project' => $project,
            'requirements' => $grouped,
            'applied' => $applied,
            'totalsByFolder' => $totalsByFolder,
            'sectorCatalog' => $sectorCatalog,
            'studyFolders' => $studyFolders,
            'studyAssignments' => $studyAssignments,
            'specialistOptions' => $specialistOptions,
            'specialistDetails' => $specialistDetails,
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $this->authorizeProjectMutation();

        $project->load('sectores');
        $studyFolders = $this->mappingService->applicableStudyFolders($project)->all();

        $data = $request->validate([
            'aplica' => ['array'],
            'aplica.*' => ['integer', 'exists:requirements,id'],
            'study_specialists' => ['array'],
            'study_specialists.*' => array_filter([
                'nullable',
                'integer',
                Schema::hasTable('specialists') ? 'exists:specialists,id' : null,
            ]),
        ]);

        $ids = collect($data['aplica'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $existing = $project->requisitos()->newPivotQuery()->pluck('activated_at', 'requirement_id');
        $now = now();
        $syncPayload = $ids->mapWithKeys(fn (int $id) => [
            $id => ['activated_at' => $existing->get($id) ?: $now],
        ])->all();

        $attached = $ids->diff($existing->keys()->map(fn ($id) => (int) $id));
        $detached = $existing->keys()->map(fn ($id) => (int) $id)->diff($ids);

        $project->requisitos()->sync($syncPayload);

        foreach ($attached as $requirementId) {
            \App\Models\OperationalActivityEvent::query()->create([
                'project_id' => $project->id,
                'requirement_id' => $requirementId,
                'event_type' => 'requirement_activated',
                'source' => 'orbit_checklist',
                'new_value' => ['active' => true],
                'occurred_at' => $now,
            ]);
        }
        foreach ($detached as $requirementId) {
            \App\Models\OperationalActivityEvent::query()->create([
                'project_id' => $project->id,
                'requirement_id' => $requirementId,
                'event_type' => 'requirement_deactivated',
                'source' => 'orbit_checklist',
                'old_value' => ['active' => true],
                'new_value' => ['active' => false],
                'occurred_at' => $now,
            ]);
        }
        app(ProjectStatusService::class)->setByName($project, 'Formulación y presentación');

        if (Schema::hasTable('project_study_specialist_assignments') && Schema::hasTable('specialists')) {
            $incomingAssignments = collect($data['study_specialists'] ?? []);
            foreach ($studyFolders as $folder) {
                $userId = $incomingAssignments->has($folder) ? (int) ($incomingAssignments->get($folder) ?: 0) : 0;
                if ($userId > 0) {
                    ProjectStudySpecialistAssignment::query()->updateOrCreate(
                        ['project_id' => $project->id, 'study_folder' => $folder],
                        ['specialist_id' => $userId, 'user_id' => null]
                    );
                } else {
                    ProjectStudySpecialistAssignment::query()
                        ->where('project_id', $project->id)
                        ->where('study_folder', $folder)
                        ->delete();
                }
            }

            $assignedSpecialists = ProjectStudySpecialistAssignment::query()
                ->with('specialist')
                ->where('project_id', $project->id)
                ->whereNotNull('specialist_id')
                ->get()
                ->pluck('specialist')
                ->filter();

            if ($assignedSpecialists->isNotEmpty()) {
                app(PlaneProvisioningService::class)->syncSpecialistsAgainstPlane($assignedSpecialists);
            }
        }

        if ($project->plane_project_id) {
            $project->forceFill([
                'plane_sync_status' => 'pending',
                'plane_last_error' => null,
            ])->save();

            ProvisionPlaneProjectJob::dispatch($project->id);
        }

        if ($request->boolean('panel_return')) {
            return redirect()
                ->route('filament.admin.resources.projects.checklist', ['record' => $project])
                ->with('status', 'Checklist actualizado.');
        }

        return redirect()->route('projects.checklist', $project)->with('status', 'Checklist actualizado.');
    }

    private function filterSectorial($requirements, array $sectorNames)
    {
        if (empty($sectorNames)) {
            return $requirements;
        }

        return $requirements->filter(function ($req) use ($sectorNames) {
            $carpeta = $this->normalizeSector($req->carpeta);
            if ($carpeta && str_contains($carpeta, 'documentos sectoriales')) {
                $reqSector = $this->normalizeSector($req->sector);
                if ($reqSector === '') {
                    return false;
                }
                return $this->sectorMatches($reqSector, $sectorNames);
            }
            return true;
        })->values();
    }

    private function sectorMatches(string $reqSector, array $projectSectors): bool
    {
        foreach ($projectSectors as $projectSector) {
            if ($reqSector === $projectSector) {
                return true;
            }

            $reqTokens = collect(explode(' ', str_replace(' y ', ' ', $reqSector)))
                ->filter(fn ($t) => $t !== '')
                ->values()
                ->all();
            $projTokens = collect(explode(' ', str_replace(' y ', ' ', $projectSector)))
                ->filter(fn ($t) => $t !== '')
                ->values()
                ->all();

            if (! empty($reqTokens) && ! empty($projTokens)) {
                sort($reqTokens);
                sort($projTokens);
                if ($reqTokens === $projTokens) {
                    return true;
                }
            }

            if (str_contains($reqSector, $projectSector) || str_contains($projectSector, $reqSector)) {
                return true;
            }
        }

        return false;
    }

    private function projectSectorCatalog(Project $project): array
    {
        $primary = $project->sectores->first(fn ($sector) => (bool) ($sector->pivot->is_primary ?? false));
        if (! $primary) {
            $primary = $project->sectores->first();
        }
        $secondary = $project->sectores->filter(fn ($s) => ! (bool) ($s->pivot->is_primary ?? false));
        if ($primary) {
            $secondary = $secondary->reject(fn ($s) => (int) $s->id === (int) $primary->id);
        }

        $ordered = collect();
        if ($primary) {
            $ordered->push([
                'name' => $primary->nombre,
                'normalized' => $this->normalizeSector($primary->nombre),
                'is_primary' => true,
            ]);
        }
        foreach ($secondary as $sector) {
            $ordered->push([
                'name' => $sector->nombre,
                'normalized' => $this->normalizeSector($sector->nombre),
                'is_primary' => false,
            ]);
        }

        $ordered = $ordered->filter(fn ($s) => $s['normalized'] !== '')->values();

        return [
            'ordered' => $ordered->all(),
            'names' => $ordered->pluck('normalized')->all(),
        ];
    }

    private function buildRequirementGroups($items): array
    {
        $byId = $items->keyBy('id');
        $groups = [];

        foreach ($items as $req) {
            $parentId = $req->parent_id;
            if ($parentId && $byId->has($parentId)) {
                $parentKey = $parentId;
            } else {
                $parentKey = $req->id;
            }

            if (! isset($groups[$parentKey])) {
                $groups[$parentKey] = [
                    'parent' => $byId->get($parentKey),
                    'children' => [],
                ];
            }

            if ($parentId && $byId->has($parentId)) {
                $groups[$parentKey]['children'][] = $req;
            }
        }

        foreach ($groups as &$group) {
            usort($group['children'], function ($a, $b) {
                return strnatcasecmp((string) $a->orden, (string) $b->orden)
                    ?: strnatcasecmp((string) $a->codigo_interno, (string) $b->codigo_interno);
            });
        }

        return array_values($groups);
    }

    private function normalizeSector(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = \Illuminate\Support\Str::ascii($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function authorizeProjectMutation(): void
    {
        $user = auth()->user();
        if (! $user || ! $user->canMutateProjects()) {
            abort(403);
        }
    }
}
