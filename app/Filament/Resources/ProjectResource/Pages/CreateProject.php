<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;
    protected int $primarySectorId = 0;
    protected array $secondarySectorIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['drive_folder_id'] = ProjectResource::extractDriveFolderId($data['ruta_drive'] ?? null);
        $this->validatePrimaryAndSecondary($data);
        $this->primarySectorId = (int) ($data['sector_principal_id'] ?? 0);
        $this->secondarySectorIds = collect($data['sectores_secundarios'] ?? [])->map(fn ($id) => (int) $id)->all();
        unset($data['sector_principal_id'], $data['sectores_secundarios']);

        return $data;
    }

    protected function afterCreate(): void
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
