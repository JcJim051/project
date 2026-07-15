<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Jobs\ProvisionPlaneProjectJob;
use App\Models\PlaneSyncRun;
use App\Services\PlaneProvisioningService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageProjectPlane extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.manage-project-plane';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->hydratePlaneViewData();
    }

    protected function getViewData(): array
    {
        $this->hydratePlaneViewData();

        return $this->viewData;
    }

    public function getHeading(): string
    {
        $name = $this->record?->nombre_clave ?: $this->record?->nombre ?: 'Proyecto';

        return 'Capa operativa: ' . $name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncPlaneTeam')
                ->label('Sincronizar equipo Plane')
                ->icon('heroicon-o-user-group')
                ->visible(fn (): bool => $this->canManagePlane())
                ->action(function (): void {
                    $this->queuePlaneSync('team');
                }),
            Action::make('syncPlaneTasks')
                ->label('Sincronizar tareas Plane')
                ->icon('heroicon-o-rectangle-stack')
                ->visible(fn (): bool => $this->canManagePlane())
                ->action(function (): void {
                    $this->queuePlaneSync('tasks');
                }),
            Action::make('syncPlaneFull')
                ->label('Sincronización completa')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => $this->canManagePlane())
                ->action(function (): void {
                    $this->queuePlaneSync('full');
                }),
            Action::make('checklist')
                ->label('Ir a Requisitos')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(ProjectResource::getUrl('checklist', ['record' => $this->record])),
            Action::make('gestionar')
                ->label('Ir a Gestionar')
                ->icon('heroicon-o-folder')
                ->url(ProjectResource::getUrl('manage', ['record' => $this->record])),
        ];
    }

    private function hydratePlaneViewData(): void
    {
        $this->viewData = [
            'project' => $this->record,
            'planeStatus' => $this->planeStatus(),
            'planeTeamStatus' => $this->planeTeamStatus(),
            'planeSyncRuns' => $this->planeSyncRuns(),
        ];
    }

    private function canManagePlane(): bool
    {
        return (bool) auth()->user()?->isAdminUser();
    }

    private function planeStatus(): array
    {
        $this->record->loadCount([
            'planeTaskLinks as plane_task_links_total',
            'planeTaskLinks as plane_task_links_active' => fn ($query) => $query->where('status', 'active'),
        ]);

        return [
            'status' => (string) ($this->record->plane_sync_status ?? 'not_configured'),
            'status_label' => match ($this->record->plane_sync_status) {
                'provisioned' => 'Sincronizado',
                'pending' => 'Sincronizando',
                'failed' => 'Con novedad',
                default => 'Sin configuración',
            },
            'status_class' => match ($this->record->plane_sync_status) {
                'provisioned' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                'pending' => 'border-amber-200 bg-amber-50 text-amber-700',
                'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                default => 'border-gray-200 bg-gray-50 text-gray-600',
            },
            'last_error' => $this->record->plane_last_error,
            'last_provisioned_at' => optional($this->record->plane_last_provisioned_at)?->format('Y-m-d H:i:s'),
            'project_url' => $this->record->resolved_plane_project_url,
            'tasks_total' => (int) ($this->record->plane_task_links_total ?? 0),
            'tasks_active' => (int) ($this->record->plane_task_links_active ?? 0),
        ];
    }

    private function planeTeamStatus(): array
    {
        if (! $this->record?->plane_project_id) {
            return [
                'success' => false,
                'status' => 'missing_project',
                'message' => 'El proyecto aún no existe en Plane.',
                'members' => [],
                'found_count' => 0,
                'missing_count' => 0,
                'in_project_count' => 0,
            ];
        }

        return app(PlaneProvisioningService::class)->projectTeamStatus($this->record);
    }

    private function planeSyncRuns(): array
    {
        if (! $this->canManagePlane()) {
            return [];
        }

        return $this->record->planeSyncRuns()
            ->with('initiatedBy:id,name,email')
            ->limit(12)
            ->get()
            ->map(function (PlaneSyncRun $run) {
                return [
                    'id' => $run->id,
                    'mode' => $run->mode,
                    'mode_label' => match ($run->mode) {
                        'team' => 'Equipo',
                        'tasks' => 'Tareas',
                        default => 'Completa',
                    },
                    'status' => $run->status,
                    'status_label' => match ($run->status) {
                        'queued' => 'En cola',
                        'running' => 'Ejecutando',
                        'succeeded' => 'Exitosa',
                        'failed' => 'Falló',
                        default => ucfirst((string) $run->status),
                    },
                    'status_class' => match ($run->status) {
                        'queued' => 'border-amber-200 bg-amber-50 text-amber-700',
                        'running' => 'border-sky-200 bg-sky-50 text-sky-700',
                        'succeeded' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                        'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                        default => 'border-gray-200 bg-gray-50 text-gray-600',
                    },
                    'message' => (string) ($run->message ?: ''),
                    'error_details' => (string) ($run->error_details ?: ''),
                    'attempt_count' => (int) $run->attempt_count,
                    'started_at' => optional($run->started_at)->format('Y-m-d H:i:s'),
                    'finished_at' => optional($run->finished_at)->format('Y-m-d H:i:s'),
                    'created_at' => optional($run->created_at)->format('Y-m-d H:i:s'),
                    'initiated_by' => (string) ($run->initiatedBy?->name ?: $run->initiatedBy?->email ?: 'Sistema'),
                ];
            })
            ->all();
    }

    private function queuePlaneSync(string $mode): void
    {
        $activeRun = $this->record->planeSyncRuns()
            ->whereIn('status', ['queued', 'running'])
            ->latest('id')
            ->first();

        if ($activeRun) {
            Notification::make()
                ->title('Ya hay una sincronización en curso')
                ->body('Orbit ya tiene una corrida Plane activa para este proyecto. Revisa el historial antes de volver a lanzarla.')
                ->warning()
                ->send();

            $this->record->refresh();
            $this->hydratePlaneViewData();

            return;
        }

        $this->record->forceFill([
            'plane_sync_status' => 'pending',
            'plane_last_error' => null,
        ])->save();

        $run = PlaneSyncRun::query()->create([
            'project_id' => $this->record->id,
            'initiated_by_user_id' => auth()->id(),
            'mode' => $mode,
            'status' => 'queued',
            'job_unique_key' => 'plane-project:' . $this->record->id . ':' . $mode,
            'message' => 'Sincronización enviada a la cola.',
        ]);

        ProvisionPlaneProjectJob::dispatch($this->record->id, $mode, $run->id);

        $this->record->refresh();
        $this->hydratePlaneViewData();

        Notification::make()
            ->title('Sincronización Plane enviada')
            ->body(match ($mode) {
                'team' => 'Orbit intentará sincronizar únicamente el equipo del proyecto con Plane.',
                'tasks' => 'Orbit intentará sincronizar únicamente módulos y tareas del proyecto en Plane.',
                default => 'Orbit reenviará la capa operativa completa del proyecto a Plane en segundo plano.',
            })
            ->success()
            ->send();
    }
}
