<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;
    protected int $primarySectorId = 0;
    protected array $secondarySectorIds = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['drive_folder_id'] = ProjectResource::extractDriveFolderId($data['ruta_drive'] ?? null);
        $this->validatePrimaryAndSecondary($data);
        $this->primarySectorId = (int) ($data['sector_principal_id'] ?? 0);
        $this->secondarySectorIds = collect($data['sectores_secundarios'] ?? [])->map(fn ($id) => (int) $id)->all();
        unset($data['sector_principal_id'], $data['sectores_secundarios']);

        return $data;
    }

    protected function afterSave(): void
    {
        $primaryId = $this->primarySectorId;
        $secondaryIds = collect($this->secondarySectorIds)->filter()->unique()->values();

        $syncData = [];
        if ($primaryId > 0) {
            $syncData[$primaryId] = ['is_primary' => true];
        }
        foreach ($secondaryIds as $sectorId) {
            $sectorId = (int) $sectorId;
            if ($sectorId <= 0 || $sectorId === $primaryId) {
                continue;
            }
            $syncData[$sectorId] = ['is_primary' => false];
        }

        $this->record->sectores()->sync($syncData);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing('sectores');
        $primary = $this->record->sectores->first(fn ($sector) => (bool) ($sector->pivot->is_primary ?? false));
        if (!$primary) {
            $primary = $this->record->sectores->first();
        }
        $secondary = $this->record->sectores
            ->reject(fn ($sector) => (bool) ($sector->pivot->is_primary ?? false))
            ->pluck('id')
            ->values()
            ->all();
        if ($primary) {
            $secondary = collect($secondary)
                ->reject(fn ($id) => (int) $id === (int) $primary->id)
                ->values()
                ->all();
        }

        $data['sector_principal_id'] = $primary?->id;
        $data['sectores_secundarios'] = $secondary;

        return $data;
    }

    private function validatePrimaryAndSecondary(array $data): void
    {
        $primaryId = (int) ($data['sector_principal_id'] ?? 0);
        $secondaryIds = collect($data['sectores_secundarios'] ?? [])->map(fn ($id) => (int) $id);
        if ($primaryId > 0 && $secondaryIds->contains($primaryId)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'sectores_secundarios' => 'El sector principal no puede repetirse como secundario.',
            ]);
        }
    }
}
