<?php

namespace App\Jobs;

use App\Events\GamificationActivityTriggered;
use App\Models\AttachmentPackageRun;
use App\Models\User;
use App\Services\AttachmentPackageService;
use App\Services\OfficialEmailNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification as FilamentNotification;

class GenerateAttachmentPackageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;
    public bool $failOnTimeout = true;

    public function __construct(private readonly int $runId)
    {
    }

    public function handle(AttachmentPackageService $service): void
    {
        $run = AttachmentPackageRun::query()->find($this->runId);
        if (!$run) {
            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
            'meta' => array_merge(is_array($run->meta) ? $run->meta : [], [
                'stage_label' => 'Iniciando',
                'stage_percent' => 1,
                'heartbeat_at' => now()->toDateTimeString(),
            ]),
        ]);

        try {
            $service->generateAndUpload($run);
            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'meta' => array_merge(is_array($run->meta) ? $run->meta : [], [
                    'stage_label' => 'Finalizado',
                    'stage_percent' => 100,
                    'heartbeat_at' => now()->toDateTimeString(),
                ]),
            ]);

            $this->notifyRequester($run, true);
            $this->notifyProjectTeamPackageGenerated($run);

            if ((int) $run->user_id > 0 && (int) $run->project_id > 0) {
                event(new GamificationActivityTriggered('pdf_package_generated', (int) $run->user_id, [
                    'project_id' => (int) $run->project_id,
                    'metadata' => ['run_id' => (int) $run->id],
                ]));
            }
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
                'meta' => array_merge(is_array($run->meta) ? $run->meta : [], [
                    'stage_label' => 'Fallo en la generacion',
                    'stage_percent' => 100,
                    'heartbeat_at' => now()->toDateTimeString(),
                ]),
            ]);
            $this->notifyRequester($run, false);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $run = AttachmentPackageRun::query()->find($this->runId);
        if (!$run) {
            return;
        }

        if (in_array($run->status, ['success', 'failed'], true)) {
            return;
        }

        $run->update([
            'status' => 'failed',
            'error_message' => $e->getMessage() ?: 'La tarea fallo o excedio el tiempo limite.',
            'finished_at' => now(),
            'meta' => array_merge(is_array($run->meta) ? $run->meta : [], [
                'stage_label' => 'Fallo en la generacion',
                'stage_percent' => 100,
                'heartbeat_at' => now()->toDateTimeString(),
            ]),
        ]);
        $this->notifyRequester($run, false);
    }


    private function notifyProjectTeamPackageGenerated(AttachmentPackageRun $run): void
    {
        $project = $run->project;
        if (!$project) {
            return;
        }

        $ids = collect([
            (int) $project->formulador_id,
            (int) $project->estructurador_id,
            (int) $run->user_id,
        ])->filter(fn ($id) => $id > 0);

        $directorIds = User::query()
            ->where(function ($query): void {
                $query->where('is_admin', true)
                    ->orWhereHas('roles', fn ($q) => $q->whereIn('slug', ['admin', 'director', 'formulador_maestro']));
            })
            ->pluck('id');

        $ids = $ids->merge($directorIds)->unique()->values();
        if ($ids->isEmpty()) {
            return;
        }

        $users = User::query()->whereIn('id', $ids->all())->get();
        if ($users->isEmpty()) {
            return;
        }

        $projectName = (string) ($project->nombre_clave ?: $project->nombre ?: ('Proyecto #' . $project->id));
        $outputLabel = strtoupper((string) ($run->output_type ?: 'zip'));
        $notification = FilamentNotification::make()
            ->title('Carteras generadas')
            ->body("{$projectName}: ya está listo el archivo {$outputLabel} de carteras.")
            ->icon('heroicon-o-document-check')
            ->iconColor('success')
            ->actions([
                NotificationAction::make('open')
                    ->label('Ver paquete')
                    ->url(route('filament.admin.resources.projects.attachments', ['record' => $run->project_id]), shouldOpenInNewTab: false),
            ]);

        foreach ($users as $user) {
            $user->notifyNow($notification->toDatabase());
            DatabaseNotificationsSent::dispatch($user);
        }

        app(OfficialEmailNotificationService::class)->sendProjectEvent(
            $project,
            $users,
            'Carteras generadas: ' . $projectName,
            'Carteras generadas',
            'Paquete PDF listo',
            "Ya está listo el archivo {$outputLabel} de carteras.",
            route('filament.admin.resources.projects.attachments', ['record' => $run->project_id]),
            'Ver paquete'
        );
    }
    private function notifyRequester(AttachmentPackageRun $run, bool $success): void
    {
        $user = User::query()->find((int) $run->user_id);
        if (!$user) {
            return;
        }

        $projectName = (string) ($run->project?->nombre_clave ?: $run->project?->nombre ?: ('Proyecto #' . $run->project_id));
        $outputLabel = strtoupper((string) ($run->output_type ?: 'zip'));

        $notification = FilamentNotification::make()
            ->title($success ? 'Paquete PDF finalizado' : 'Paquete PDF falló')
            ->body($success
                ? "{$projectName}: ya está listo el archivo {$outputLabel}."
                : "{$projectName}: no se pudo generar el paquete. " . ((string) $run->error_message ?: 'Revisa el historial para más detalle.'))
            ->icon($success ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->iconColor($success ? 'success' : 'danger')
            ->actions([
                NotificationAction::make('open')
                    ->label('Ver paquete')
                    ->url(route('filament.admin.resources.projects.attachments', ['record' => $run->project_id]), shouldOpenInNewTab: false),
            ]);

        $user->notifyNow($notification->toDatabase());
        DatabaseNotificationsSent::dispatch($user);
    }
}
