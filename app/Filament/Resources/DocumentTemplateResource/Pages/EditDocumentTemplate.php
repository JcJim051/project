<?php

namespace App\Filament\Resources\DocumentTemplateResource\Pages;

use App\Filament\Resources\DocumentTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditDocumentTemplate extends EditRecord
{
    protected static string $resource = DocumentTemplateResource::class;

    protected ?string $oldFilePath = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->oldFilePath = $this->record->ruta_archivo;

        return $data;
    }

    protected function afterSave(): void
    {
        $newFilePath = $this->record->ruta_archivo;
        if (
            $this->oldFilePath &&
            $newFilePath &&
            $this->oldFilePath !== $newFilePath &&
            Storage::disk('local')->exists($this->oldFilePath)
        ) {
            Storage::disk('local')->delete($this->oldFilePath);
        }
    }
}
