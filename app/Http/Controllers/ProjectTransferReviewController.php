<?php

namespace App\Http\Controllers;

use App\Events\GamificationActivityTriggered;
use App\Models\Project;
use App\Models\ProjectTransferRequest;
use App\Models\ProjectTransferRequestRequirementComment;
use App\Models\RequirementEvidence;
use App\Models\User;
use App\Services\MgaTransferAuthorizationService;
use App\Services\OfficialEmailNotificationService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectTransferReviewController extends Controller
{
    public function show(ProjectTransferRequest $transferRequest)
    {
        $this->authorizeView($transferRequest);

        $project = $transferRequest->project()->with(['sectores', 'formulador', 'estructurador'])->firstOrFail();
        $requirements = $this->getActiveRequirementsForProject($project);
        $evidenceByRequirement = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->where('in_drive', true)
            ->get()
            ->groupBy('requirement_id');

        $comments = $transferRequest->requirementComments()
            ->with('author:id,name')
            ->get()
            ->keyBy('requirement_id');

        $requirementsByGroup = $requirements->groupBy(fn ($req) => $this->detectTopGroupCode((string) $req->carpeta) ?? '99');

        return view('filament.resources.project-transfer-request-resource.pages.review-transfer-request', [
            'transferRequest' => $transferRequest->load(['requestedBy', 'decidedBy']),
            'project' => $project,
            'requirementsByGroup' => $requirementsByGroup,
            'evidenceByRequirement' => $evidenceByRequirement,
            'comments' => $comments,
            'groupLabels' => [
                '01' => '01 Formulación',
                '02' => '02 Presupuesto',
                '03' => '03 Certificaciones',
                '04' => '04 Licencias y Permisos',
                '05' => '05 Estudios y Diseños',
                '99' => 'Otros',
            ],
        ]);
    }

    public function saveComments(Request $request, ProjectTransferRequest $transferRequest): RedirectResponse|JsonResponse
    {
        $this->authorizeDecide($transferRequest);

        $payload = $request->validate([
            'comments' => ['array'],
            'comments.*' => ['nullable', 'string'],
        ]);

        $comments = collect($payload['comments'] ?? [])->map(fn ($v) => trim((string) $v));

        DB::transaction(function () use ($transferRequest, $comments): void {
            foreach ($comments as $requirementId => $comment) {
                $requirementId = (int) $requirementId;
                if ($requirementId <= 0) {
                    continue;
                }

                if ($comment === '') {
                    ProjectTransferRequestRequirementComment::query()
                        ->where('project_transfer_request_id', $transferRequest->id)
                        ->where('requirement_id', $requirementId)
                        ->delete();
                    continue;
                }

                ProjectTransferRequestRequirementComment::query()->updateOrCreate(
                    [
                        'project_transfer_request_id' => $transferRequest->id,
                        'requirement_id' => $requirementId,
                    ],
                    [
                        'author_user_id' => (int) auth()->id(),
                        'comment' => $comment,
                    ]
                );
            }
        });

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Comentarios guardados.',
            ]);
        }

        return back()->with('status', 'Comentarios guardados.');
    }

    public function decide(Request $request, ProjectTransferRequest $transferRequest, string $decision): RedirectResponse
    {
        $this->authorizeView($transferRequest);

        abort_unless(in_array($decision, ['approve', 'reject'], true), 404);
        abort_if($transferRequest->status !== 'pending', 422, 'La solicitud ya fue decidida.');

        $validated = $request->validate([
            'decision_note' => ['required', 'string'],
            'approval_scope' => ['nullable', 'in:direction,planning'],
        ], [
            'decision_note.required' => 'El comentario es obligatorio.',
        ]);

        $user = $request->user();
        /** @var MgaTransferAuthorizationService $service */
        $service = app(MgaTransferAuthorizationService::class);
        $scope = (string) ($validated['approval_scope'] ?? '');
        if ($scope === '') {
            $scope = $user?->canAuthorizeDirectorMgaTransfer() ? 'direction' : 'planning';
        }

        if ($scope === 'planning') {
            abort_unless($service->requiresPlanningApproval(), 422, 'La aprobación de Planeación AIM no está activa.');
            abort_unless($user && $user->canAuthorizePlanningMgaTransfer(), 403);
            abort_if((string) $transferRequest->planning_status !== 'pending', 422, 'Planeación AIM ya decidió esta solicitud.');
        } else {
            abort_unless($user && $user->canAuthorizeDirectorMgaTransfer(), 403);
            abort_if((string) $transferRequest->director_status !== 'pending', 422, 'Dirección ya decidió esta solicitud.');
        }

        $commentLines = $transferRequest->requirementComments()
            ->with('requirement:id,numeracion,nombre_documento,requisito')
            ->get()
            ->map(function (ProjectTransferRequestRequirementComment $comment): string {
                $req = $comment->requirement;
                if (!$req) {
                    return '';
                }
                $title = trim((string) ($req->nombre_documento ?: $req->requisito));
                $code = trim((string) ($req->numeracion ?? ''));
                $prefix = $code !== '' ? $code . ' ' : '';

                return '- ' . $prefix . $title . ': ' . trim((string) $comment->comment);
            })
            ->filter()
            ->values();

        $consolidated = trim((string) $validated['decision_note']);
        if ($commentLines->isNotEmpty()) {
            $consolidated .= "\n\nObservaciones por requisito:\n" . $commentLines->implode("\n");
        }

        $beforeStatus = (string) $transferRequest->status;
        $transferRequest = $scope === 'planning'
            ? $service->decidePlanning($transferRequest, $user, $decision, $consolidated)
            : $service->decideDirection($transferRequest, $user, $decision, $consolidated);

        if ((string) $transferRequest->status === 'approved' && $beforeStatus !== 'approved' && (int) $transferRequest->requested_by_user_id > 0) {
            event(new GamificationActivityTriggered('mga_approved', (int) $transferRequest->requested_by_user_id, [
                'project_id' => (int) $transferRequest->project_id,
                'metadata' => ['transfer_request_id' => (int) $transferRequest->id],
            ]));
        }

        $this->notifyProjectTeamDecision($transferRequest, $decision, $scope, $service);

        return redirect()
            ->route('filament.admin.resources.project-transfer-requests.index')
            ->with('status', $this->decisionFlashMessage($transferRequest, $decision, $scope, $service));
    }

    private function authorizeView(ProjectTransferRequest $transferRequest): void
    {
        $user = auth()->user();
        abort_unless($user && $user->canAuthorizeMgaTransfer(), 403);

        if ((int) $transferRequest->project_id <= 0) {
            abort(404);
        }
    }

    private function authorizeDecide(ProjectTransferRequest $transferRequest): void
    {
        $this->authorizeView($transferRequest);
        abort_if($transferRequest->status !== 'pending', 422, 'Esta solicitud ya tiene decisión.');
    }

    private function getActiveRequirementsForProject(Project $project)
    {
        $requirements = $project->requisitos()
            ->where('requirements.visible', true)
            ->orderBy('carpeta')
            ->orderByRaw('custom_project_id IS NOT NULL')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->get();

        return $this->filterSectorial($requirements, $project);
    }

    private function filterSectorial($requirements, Project $project)
    {
        $project->loadMissing('sectores');
        $sectorNames = $project->sectores
            ->pluck('nombre')
            ->map(fn ($name) => $this->normalizeSector($name))
            ->filter()
            ->all();

        if (empty($sectorNames)) {
            return $requirements;
        }

        return $requirements->filter(function ($req) use ($sectorNames) {
            $carpeta = $this->normalizeSector($req->carpeta);
            if ($carpeta && str_contains($carpeta, 'documentos sectoriales')) {
                $reqSector = $this->normalizeSector($req->sector);
                if ($reqSector === '') {
                    return true;
                }

                return in_array($reqSector, $sectorNames, true);
            }

            return true;
        })->values();
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

    private function detectTopGroupCode(string $folder): ?string
    {
        if (preg_match('/^\s*(\d{1,2})\b/u', $folder, $m)) {
            return str_pad((string) ((int) $m[1]), 2, '0', STR_PAD_LEFT);
        }

        $normalized = strtolower(\Illuminate\Support\Str::ascii($folder));
        if (str_contains($normalized, 'formulacion')) {
            return '01';
        }
        if (str_contains($normalized, 'presupuesto')) {
            return '02';
        }
        if (str_contains($normalized, 'certificacion')) {
            return '03';
        }
        if (str_contains($normalized, 'licencias') || str_contains($normalized, 'permisos')) {
            return '04';
        }
        if (str_contains($normalized, 'estudios') || str_contains($normalized, 'disenos')) {
            return '05';
        }

        return null;
    }

    private function notifyProjectTeamDecision(ProjectTransferRequest $transferRequest, string $decision, string $scope, MgaTransferAuthorizationService $service): void
    {
        $project = $transferRequest->project()->first();
        if (!$project) {
            return;
        }

        $ids = collect([
            (int) $project->formulador_id,
            (int) $project->estructurador_id,
            (int) $transferRequest->requested_by_user_id,
        ])->filter(fn ($id) => $id > 0);

        if ($scope === 'planning') {
            $directorIds = User::query()
                ->where(function ($query): void {
                    $query->where('is_admin', true)
                        ->orWhereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'director', 'formulador_maestro']));
                })
                ->pluck('id');
            $ids = $ids->merge($directorIds);
        }

        $ids = $ids->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $users = User::query()->whereIn('id', $ids->all())->get();
        if ($users->isEmpty()) {
            return;
        }

        $isApproved = $decision === 'approve';
        $scopeLabel = $scope === 'planning' ? 'Planeación AIM' : 'Dirección';
        $projectName = (string) ($project->nombre_clave ?: $project->nombre ?: ('Proyecto #' . $project->id));
        $title = $isApproved ? "{$scopeLabel} aprobó la solicitud" : "{$scopeLabel} observó la solicitud";
        if ($isApproved && $service->isApprovalComplete($transferRequest)) {
            $body = "{$projectName}: la aprobación interna quedó completa y ya se pueden generar carteras.";
        } elseif ($isApproved) {
            $pending = $scope === 'planning' ? 'Dirección' : 'Planeación AIM';
            $body = "{$projectName}: {$scopeLabel} aprobó. Sigue pendiente aprobación de {$pending}.";
        } else {
            $body = "{$projectName}: {$scopeLabel} registró observaciones. Corrige y envía una nueva solicitud.";
        }

        $notification = FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon($isApproved ? 'heroicon-o-check-circle' : 'heroicon-o-exclamation-triangle')
            ->iconColor($isApproved ? 'success' : 'warning')
            ->actions([
                NotificationAction::make('open')
                    ->label('Abrir gestionar')
                    ->url(route('filament.admin.resources.projects.manage', ['record' => $project]), shouldOpenInNewTab: false),
            ]);

        foreach ($users as $targetUser) {
            $targetUser->notifyNow($notification->toDatabase());
            DatabaseNotificationsSent::dispatch($targetUser);
        }

        app(OfficialEmailNotificationService::class)->sendMgaDecision(
            $project,
            $users,
            $decision,
            $scopeLabel . ': ' . ($transferRequest->decision_note ?: '')
        );
    }
    private function decisionFlashMessage(ProjectTransferRequest $transferRequest, string $decision, string $scope, MgaTransferAuthorizationService $service): string
    {
        $scopeLabel = $scope === 'planning' ? 'Planeación AIM' : 'Dirección';
        if ($decision !== 'approve') {
            return "{$scopeLabel} registró observaciones. El equipo deberá enviar una nueva solicitud cuando corrija.";
        }
        if ($service->isApprovalComplete($transferRequest)) {
            return 'Aprobación interna completa. El módulo de carteras quedó habilitado.';
        }
        $pending = $scope === 'planning' ? 'Dirección' : 'Planeación AIM';
        return "{$scopeLabel} aprobó. Sigue pendiente aprobación de {$pending}.";
    }

}
