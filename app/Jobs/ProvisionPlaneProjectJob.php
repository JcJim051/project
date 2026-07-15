<?php

namespace App\Jobs;

use App\Filament\Resources\ProjectResource;
use App\Models\PlaneSyncRun;
use App\Models\Project;
use App\Models\User;
use App\Services\PlaneProvisioningService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProvisionPlaneProjectJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;
    public bool $failOnTimeout = true;
    public int $tries = 5;
    public int $uniqueFor = 1800;

    public function __construct(
        private readonly int $projectId,
        private readonly string $mode = 'full',
        private readonly ?int $planeSyncRunId = null,
    )
    {
        $this->onConnection('database');
    }

    public function handle(PlaneProvisioningService $service): void
    {
        $project = Project::query()->find($this->projectId);
        if (! $project) {
            return;
        }

        $run = $this->syncRun();
        if ($run && $run->status !== 'running') {
            $run->forceFill([
                'status' => 'running',
                'started_at' => $run->started_at ?: now(),
                'attempt_count' => max(1, $this->attempts()),
                'message' => 'Sincronización en ejecución.',
                'error_details' => null,
            ])->save();
        }

        $result = match ($this->mode) {
            'team' => $service->syncProjectTeam($project),
            'tasks' => $service->syncProjectTasks($project),
            default => $service->provisionProject($project),
        };
        if ($result['success']) {
            $project->forceFill([
                'plane_sync_status' => 'provisioned',
                'plane_project_id' => $result['plane_project_id'] ?? $project->plane_project_id,
                'plane_project_url' => $result['plane_project_url'] ?? $project->plane_project_url,
                'plane_last_provisioned_at' => now(),
                'plane_last_error' => null,
            ])->save();

            if ($run) {
                $run->forceFill([
                    'status' => 'succeeded',
                    'message' => (string) ($result['message'] ?? $this->successMessage($project)),
                    'error_details' => null,
                    'finished_at' => now(),
                    'attempt_count' => max(1, $this->attempts()),
                ])->save();
            }

            $this->notifySuccess($project, $run, (string) ($result['message'] ?? 'Sincronización terminada correctamente.'));

            return;
        }

        $message = (string) ($result['message'] ?? 'La provisión en Plane falló.');
        if ($this->shouldRetry($message) && $this->attempts() < $this->tries) {
            $project->forceFill([
                'plane_sync_status' => 'pending',
                'plane_project_id' => $result['plane_project_id'] ?? $project->plane_project_id,
                'plane_project_url' => $result['plane_project_url'] ?? $project->plane_project_url,
                'plane_last_error' => $message,
            ])->save();

            if ($run) {
                $run->forceFill([
                    'status' => 'queued',
                    'message' => 'Plane respondió con una novedad transitoria. Orbit reintentará automáticamente.',
                    'error_details' => $message,
                    'attempt_count' => max(1, $this->attempts()),
                ])->save();
            }

            $this->release($this->backoffSeconds());

            return;
        }

        $project->forceFill([
            'plane_sync_status' => $result['status'] ?? 'failed',
            'plane_project_id' => $result['plane_project_id'] ?? $project->plane_project_id,
            'plane_project_url' => $result['plane_project_url'] ?? $project->plane_project_url,
            'plane_last_error' => $message,
        ])->save();

        if ($run) {
            $run->forceFill([
                'status' => 'failed',
                'message' => 'La sincronización terminó con error.',
                'error_details' => $message,
                'finished_at' => now(),
                'attempt_count' => max(1, $this->attempts()),
            ])->save();
        }

        throw new \RuntimeException($message);
    }

    public function failed(\Throwable $e): void
    {
        $project = Project::query()->find($this->projectId);
        if (! $project) {
            return;
        }

        $project->forceFill([
            'plane_sync_status' => 'failed',
            'plane_last_error' => $e->getMessage() ?: 'La provisión en Plane falló.',
        ])->save();

        if ($run = $this->syncRun()) {
            $run->forceFill([
                'status' => 'failed',
                'message' => 'La sincronización terminó con error.',
                'error_details' => $e->getMessage() ?: 'La provisión en Plane falló.',
                'finished_at' => now(),
                'attempt_count' => max(1, $this->attempts()),
            ])->save();
        }

        $this->notifyFailure($project, $run ?? null, $e->getMessage() ?: 'La provisión en Plane falló.');
    }

    public function backoff(): array
    {
        return [60, 180, 300, 600];
    }

    public function uniqueId(): string
    {
        return 'plane-project:' . $this->projectId . ':' . $this->mode;
    }

    private function syncRun(): ?PlaneSyncRun
    {
        // Jobs queued before sync history existed do not contain this property
        // in their serialized payload. isset() is safe for that legacy case.
        if (! isset($this->planeSyncRunId) || ! $this->planeSyncRunId) {
            return null;
        }

        return PlaneSyncRun::query()->find($this->planeSyncRunId);
    }

    private function notifySuccess(Project $project, ?PlaneSyncRun $run, string $message): void
    {
        $recipients = $this->successRecipients($run);
        if ($recipients->isEmpty()) {
            return;
        }

        $notification = FilamentNotification::make()
            ->title('Sincronización Plane completada')
            ->body($this->projectLabel($project) . ' · ' . Str::limit($message, 220))
            ->icon('heroicon-o-check-circle')
            ->iconColor('success')
            ->actions([
                NotificationAction::make('open')
                    ->label('Abrir capa operativa')
                    ->url(ProjectResource::getUrl('plane', ['record' => $project]), shouldOpenInNewTab: false),
            ]);

        foreach ($recipients as $user) {
            $user->notifyNow($notification->toDatabase());
            DatabaseNotificationsSent::dispatch($user);
        }
    }

    private function notifyFailure(Project $project, ?PlaneSyncRun $run, string $message): void
    {
        $recipients = $this->failureRecipients($run);
        if ($recipients->isEmpty()) {
            return;
        }

        $notification = FilamentNotification::make()
            ->title('Sincronización Plane con error')
            ->body($this->projectLabel($project) . ' · ' . Str::limit($message, 220))
            ->icon('heroicon-o-exclamation-triangle')
            ->iconColor('danger')
            ->actions([
                NotificationAction::make('open')
                    ->label('Ver detalle')
                    ->url(ProjectResource::getUrl('plane', ['record' => $project]), shouldOpenInNewTab: false),
            ]);

        foreach ($recipients as $user) {
            $user->notifyNow($notification->toDatabase());
            DatabaseNotificationsSent::dispatch($user);
        }
    }

    private function successRecipients(?PlaneSyncRun $run): Collection
    {
        $initiatorId = (int) ($run?->initiated_by_user_id ?? 0);
        if ($initiatorId <= 0) {
            return collect();
        }

        $user = User::query()->find($initiatorId);

        return $user ? collect([$user]) : collect();
    }

    private function failureRecipients(?PlaneSyncRun $run): Collection
    {
        $users = User::query()->get()->filter(fn (User $user) => $user->isAdminUser())->values();
        $initiatorId = (int) ($run?->initiated_by_user_id ?? 0);

        if ($initiatorId > 0 && ! $users->contains(fn (User $user) => (int) $user->id === $initiatorId)) {
            $initiator = User::query()->find($initiatorId);
            if ($initiator) {
                $users->push($initiator);
            }
        }

        return $users->unique('id')->values();
    }

    private function projectLabel(Project $project): string
    {
        return (string) ($project->nombre_clave ?: $project->nombre ?: ('Proyecto #' . $project->id));
    }

    private function successMessage(Project $project): string
    {
        return 'Orbit terminó la sincronización de ' . $this->projectLabel($project) . ' con Plane.';
    }

    private function shouldRetry(string $message): bool
    {
        $message = mb_strtoupper($message);

        return str_contains($message, '429')
            || str_contains($message, 'RATE_LIMIT')
            || str_contains($message, 'TIMED OUT')
            || str_contains($message, 'CURL ERROR')
            || str_contains($message, 'CONNECTION');
    }

    private function backoffSeconds(): int
    {
        $steps = $this->backoff();
        $index = max(0, min($this->attempts() - 1, count($steps) - 1));

        return $steps[$index] ?? 300;
    }
}
