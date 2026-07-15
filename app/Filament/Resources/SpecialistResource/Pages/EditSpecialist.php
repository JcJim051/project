<?php

namespace App\Filament\Resources\SpecialistResource\Pages;

use App\Filament\Resources\SpecialistResource;
use App\Services\PlaneProvisioningService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSpecialist extends EditRecord
{
    protected static string $resource = SpecialistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('retryPlaneLink')
                ->label('Reintentar vínculo con Plane')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    $result = app(PlaneProvisioningService::class)->syncSpecialistsAgainstPlane([$this->record]);
                    $this->record->refresh();
                    $this->fillForm();

                    $notification = Notification::make()
                        ->title($result['success'] ? 'Vínculo con Plane actualizado' : 'No se pudo reintentar el vínculo')
                        ->body($this->record->plane_sync_status === 'linked'
                            ? 'El especialista quedó vinculado correctamente en Plane.'
                            : ($this->record->plane_last_error ?: ($result['message'] ?? 'No fue posible resolver el especialista en Plane.')));

                    if ($result['success']) {
                        $notification->success();
                    } else {
                        $notification->danger();
                    }

                    $notification->send();
                }),
            ...parent::getHeaderActions(),
        ];
    }
}
