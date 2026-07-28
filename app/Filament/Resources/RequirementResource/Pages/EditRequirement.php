<?php

namespace App\Filament\Resources\RequirementResource\Pages;

use App\Filament\Resources\RequirementResource;
use App\Services\RequirementCloneService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRequirement extends EditRecord
{
    protected static string $resource = RequirementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('clone')
                ->label('Clonar requisito')
                ->icon('heroicon-o-document-duplicate')
                ->color('gray')
                ->visible(fn (): bool => $this->record->workflowStepLinks()->exists())
                ->form([
                    TextInput::make('name')
                        ->label('Nombre del nuevo requisito')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Se conservarán la carpeta, el origen y la ubicación dentro del flujo.'),
                ])
                ->fillForm(fn (): array => [
                    'name' => 'Copia de '.($this->record->nombre_documento ?: $this->record->requisito ?: 'requisito'),
                ])
                ->action(function (array $data): void {
                    $clone = app(RequirementCloneService::class)
                        ->cloneForWorkflow($this->record, (string) $data['name']);

                    Notification::make()
                        ->title('Requisito clonado')
                        ->body('Puedes ajustar los demás datos del nuevo requisito.')
                        ->success()
                        ->send();

                    $this->redirect(RequirementResource::getUrl('edit', ['record' => $clone]));
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
