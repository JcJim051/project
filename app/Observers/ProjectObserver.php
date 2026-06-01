<?php

namespace App\Observers;

use App\Models\Project;
use App\Models\User;
use App\Services\OfficialEmailNotificationService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Log;

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
            $this->notifyUnassignment($project, 'formulador_id');
            $this->notifyAssignment($project, 'formulador_id', false);
        }

        if ($project->wasChanged('estructurador_id')) {
            $this->notifyUnassignment($project, 'estructurador_id');
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

        Log::info('project_assignment_notification_dispatch', [
            'project_id' => (int) $project->id,
            'field' => $field,
            'target_user_id' => (int) $targetUser->id,
            'target_email' => (string) ($targetUser->email ?? ''),
            'is_new_project' => $isNewProject,
        ]);

        app(OfficialEmailNotificationService::class)->sendAssignment($project, $targetUser, $roleLabel, $isNewProject);
    }

    private function notifyUnassignment(Project $project, string $field): void
    {
        $oldUserId = (int) ($project->getOriginal($field) ?? 0);
        $newUserId = (int) ($project->{$field} ?? 0);
        if ($oldUserId <= 0 || $oldUserId === $newUserId) {
            return;
        }

        $oldUser = User::query()->find($oldUserId);
        if (! $oldUser) {
            return;
        }

        $projectName = (string) ($project->nombre_clave ?: $project->nombre ?: ('Proyecto #' . $project->id));
        $roleLabel = $field === 'formulador_id' ? 'Formulador' : 'Estructurador';
        $title = 'Asignación de proyecto actualizada';
        $body = "{$projectName} ya no está asignado para {$roleLabel}.";

        $notification = FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->icon('heroicon-o-information-circle')
            ->iconColor('warning')
            ->actions([
                NotificationAction::make('open')
                    ->label('Ver proyecto')
                    ->url(route('filament.admin.resources.projects.manage', ['record' => $project]), shouldOpenInNewTab: false),
            ]);

        $oldUser->notifyNow($notification->toDatabase());
        DatabaseNotificationsSent::dispatch($oldUser);
    }
}
