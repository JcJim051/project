<?php

namespace App\Http\Controllers;

use App\Events\GamificationActivityTriggered;
use App\Models\Project;
use App\Models\ProjectTransferRequest;
use App\Models\RequirementEvidence;
use App\Models\User;
use App\Services\MgaTransferAuthorizationService;
use App\Services\OfficialEmailNotificationService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Http\Request;

class ProjectTransferRequestController extends Controller
{
    public function send(Project $project, Request $request, MgaTransferAuthorizationService $service)
    {
        $user = $request->user();
        if (!$user || !$user->canRequestMgaTransfer()) {
            abort(403);
        }

        if ($this->hasActivePending($project)) {
            return back()->withErrors(['mga_transfer' => 'Ya existe una solicitud pendiente para este proyecto.']);
        }

        $percent = $this->overallPercent($project);
        $minPercent = max(1, min(100, (int) ($project->attachments_min_percent ?? 80)));
        if ($percent < $minPercent) {
            return back()->withErrors([
                'mga_transfer' => "Aún no puedes enviar a evaluación. Avance actual: {$percent}% (mínimo requerido: {$minPercent}%).",
            ]);
        }

        $validated = $request->validate([
            'request_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $transferRequest = $service->request($project, $user, $validated['request_note'] ?? null);
        $this->notifyEvaluators($project, $transferRequest, $user, $validated['request_note'] ?? null);
        event(new GamificationActivityTriggered('mga_submitted', (int) $user->id, [
            'project_id' => (int) $project->id,
            'metadata' => ['transfer_request_id' => (int) $transferRequest->id],
        ]));

        return back()->with('status', 'Proyecto enviado para evaluación MGA.');
    }

    public function approve(Project $project, ProjectTransferRequest $transferRequest, Request $request, MgaTransferAuthorizationService $service)
    {
        $user = $request->user();
        if (!$user || !$user->canAuthorizeMgaTransfer()) {
            abort(403);
        }

        $this->validateOwnership($project, $transferRequest);
        if ($transferRequest->status !== 'pending') {
            return back()->withErrors(['mga_transfer' => 'Esta solicitud ya fue decidida.']);
        }

        $validated = $request->validate([
            'decision_note' => ['required', 'string', 'max:2000'],
        ]);

        $service->decide($transferRequest, $user, 'approved', $validated['decision_note']);

        return back()->with('status', 'Solicitud MGA aprobada.');
    }

    public function reject(Project $project, ProjectTransferRequest $transferRequest, Request $request, MgaTransferAuthorizationService $service)
    {
        $user = $request->user();
        if (!$user || !$user->canAuthorizeMgaTransfer()) {
            abort(403);
        }

        $this->validateOwnership($project, $transferRequest);
        if ($transferRequest->status !== 'pending') {
            return back()->withErrors(['mga_transfer' => 'Esta solicitud ya fue decidida.']);
        }

        $validated = $request->validate([
            'decision_note' => ['required', 'string', 'max:2000'],
        ]);

        $service->decide($transferRequest, $user, 'rejected', $validated['decision_note']);

        return back()->with('status', 'Solicitud MGA rechazada.');
    }

    public function acknowledge(Project $project, ProjectTransferRequest $transferRequest, Request $request, MgaTransferAuthorizationService $service)
    {
        $user = $request->user();
        if (!$user || !in_array((int) $user->id, [(int) $project->formulador_id, (int) $project->estructurador_id], true)) {
            abort(403);
        }

        $this->validateOwnership($project, $transferRequest);
        if (!in_array($transferRequest->status, ['approved', 'rejected'], true)) {
            return back()->withErrors(['mga_transfer' => 'Solo puedes acusar recibido cuando la solicitud ya fue decidida.']);
        }

        $validated = $request->validate([
            'ack_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $service->acknowledge($transferRequest, $user, $validated['ack_note'] ?? null);

        return back()->with('status', 'Acuse de recibido registrado.');
    }

    private function validateOwnership(Project $project, ProjectTransferRequest $transferRequest): void
    {
        abort_unless((int) $transferRequest->project_id === (int) $project->id, 404);
    }

    private function hasActivePending(Project $project): bool
    {
        return $project->transferRequests()->where('status', 'pending')->exists();
    }

    private function overallPercent(Project $project): int
    {
        $project->loadMissing('requisitos');
        $requirements = $project->requisitos()->where('requirements.visible', true)->get();
        $total = $requirements->count();
        if ($total === 0) {
            return 0;
        }

        $done = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->where('in_drive', true)
            ->distinct('requirement_id')
            ->count('requirement_id');

        return (int) round(($done / $total) * 100);
    }

    private function notifyEvaluators(Project $project, ProjectTransferRequest $transferRequest, User $sender, ?string $note): void
    {
        $roleSlugs = ['admin', 'director', 'formulador_maestro'];
        $users = User::query()
            ->where(function ($query) use ($roleSlugs) {
                $query->where('is_admin', true)
                    ->orWhereHas('roles', fn ($q) => $q->whereIn('slug', $roleSlugs));
            })
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        $projectName = (string) ($project->nombre_clave ?: $project->nombre ?: ('Proyecto #' . $project->id));
        $subtitle = trim((string) $note);
        if ($subtitle === '') {
            $subtitle = 'Tienes una nueva asignación para evaluar en MGA.';
        }

        $notification = FilamentNotification::make()
            ->title('Nueva asignación MGA')
            ->body("{$projectName}: {$subtitle}")
            ->icon('heroicon-o-shield-check')
            ->iconColor('warning')
            ->actions([
                NotificationAction::make('open')
                    ->label('Evaluar')
                    ->url(route('filament.admin.resources.project-transfer-requests.review', ['record' => $transferRequest]), shouldOpenInNewTab: false),
            ]);

        foreach ($users as $targetUser) {
            $targetUser->notifyNow($notification->toDatabase());
            DatabaseNotificationsSent::dispatch($targetUser);
        }

        app(OfficialEmailNotificationService::class)->sendMgaSubmitted($project, $users, $sender, $note, $transferRequest);
    }
}
