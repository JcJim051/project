<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\User;
use App\Services\OfficialEmailNotificationService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification as FilamentNotification;

class ProjectObserver
{
    public function created(Project $project): void
    {
        $this->notifyAssignment($project, 'formulador_id', true);
        $this->notifyAssignment($project, 'estructurador_id', true);
    }

    public function updated(Project $project): void
    {
        if ($project->wasChanged('formulador_id')) {
            $this->notifyAssignment($project, 'formulador_id', false);
        }

        if ($project->wasChanged('estructurador_id')) {
            $this->notifyAssignment($project, 'estructurador_id', false);
        }
    }

    private function notifyAssignment(Project $project, string $field, bool $isNewProject): void
    {
        $userId = (int) ($project->{$field} ?? 0);
        if ($userId <= 0) {
            return;
        }

        $targetUser = User::query()->find($userId);
        if (!$targetUser) {
            return;
        }

        $projectName = (string) ($project->nombre_clave ?: $project->nombre ?: ('Proyecto #' . $project->id));
        $roleLabel = $field === 'formulador_id' ? 'Formulador' : 'Estructurador';

        $title = $isNewProject ? 'Nuevo proyecto asignado' : 'Asignación de proyecto actualizada';
        $body = $isNewProject
            ? "{$projectName} fue asignado para {$roleLabel}."
            : "{$projectName} tiene una nueva asignación para {$roleLabel}.";

        $notification = FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-clipboard-document-check')
            ->iconColor('info')
            ->actions([
                NotificationAction::make('open')
                    ->label('Abrir gestionar')
                    ->url(route('filament.admin.resources.projects.manage', ['record' => $project]), shouldOpenInNewTab: false),
            ]);

        $targetUser->notifyNow($notification->toDatabase());
        DatabaseNotificationsSent::dispatch($targetUser);
        app(OfficialEmailNotificationService::class)->sendAssignment($project, $targetUser, $roleLabel, $isNewProject);
    }
}
