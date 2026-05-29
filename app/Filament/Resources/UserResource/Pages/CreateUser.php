<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\UserOnboardingService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function afterCreate(): void
    {
        $isAdminRole = $this->record->roles()->where('slug', 'admin')->exists();
        $this->record->is_admin = $isAdminRole;
        $this->record->must_change_password = true;
        $this->record->save();

        $sent = app(UserOnboardingService::class)->sendWelcomeEmail($this->record);
        if (! $sent) {
            Notification::make()
                ->warning()
                ->title('Usuario creado')
                ->body('No se pudo enviar el correo de bienvenida. Revisa configuración SMTP.')
                ->send();
        }
    }
}
