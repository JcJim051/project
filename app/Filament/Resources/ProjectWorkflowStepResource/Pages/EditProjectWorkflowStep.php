<?php

namespace App\Filament\Resources\ProjectWorkflowStepResource\Pages;

use App\Filament\Resources\ProjectWorkflowStepResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProjectWorkflowStep extends EditRecord
{
    protected static string $resource = ProjectWorkflowStepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->disabled(fn (): bool => ! $this->record->canBeDeletedSafely())
                ->tooltip(fn (): ?string => $this->record->canBeDeletedSafely()
                    ? null
                    : 'No se puede eliminar porque tiene estados históricos. Desactívelo en su lugar.'),
        ];
    }
}
