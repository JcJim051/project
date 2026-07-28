<?php

namespace App\Filament\Resources\ProjectWorkflowStageResource\Pages;

use App\Filament\Resources\ProjectWorkflowStageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProjectWorkflowStage extends EditRecord
{
    protected static string $resource = ProjectWorkflowStageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->disabled(fn (): bool => ! $this->record->canBeDeletedSafely())
                ->tooltip(fn (): ?string => $this->record->canBeDeletedSafely()
                    ? null
                    : 'No se puede eliminar porque contiene elementos con estados históricos. Desactívela en su lugar.'),
        ];
    }
}
