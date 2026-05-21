<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Municipio;
use App\Services\GoogleDriveService;
use App\Services\ProjectBankExcelService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateProject extends CreateRecord
{
    protected static string $resource = ProjectResource::class;
    protected int $primarySectorId = 0;
    protected array $secondarySectorIds = [];
    protected array $municipioIds = [];
    protected array $bankProfileData = [];
    protected ?string $driveSetupWarning = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $driveMode = (string) ($data['drive_setup_mode'] ?? 'auto');
        if ($driveMode === 'manual') {
            $data['drive_folder_id'] = ProjectResource::extractDriveFolderId($data['ruta_drive'] ?? null);
        } else {
            $data = $this->createDriveStructureForProject($data);
        }

        $this->validatePrimaryAndSecondary($data);
        $this->validateProductBelongsToPrimarySector($data);
        $this->primarySectorId = (int) ($data['sector_principal_id'] ?? 0);
        $this->secondarySectorIds = collect($data['sectores_secundarios'] ?? [])->map(fn ($id) => (int) $id)->all();
        $this->municipioIds = collect($data['municipio_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values()->all();
        $this->bankProfileData = [
            'horizonte_anio_0' => $data['horizonte_anio_0'] ?? null,
            'horizonte_anio_1' => $data['horizonte_anio_1'] ?? null,
            'horizonte_anio_2' => $data['horizonte_anio_2'] ?? null,
            'horizonte_anio_3' => $data['horizonte_anio_3'] ?? null,
            'tipo_presentacion' => $data['tipo_presentacion'] ?? 'proyecto',
            'tipo_tramite' => $data['tipo_tramite'] ?? 'actualizacion',
            'codigo_dependencia' => $data['codigo_dependencia'] ?? null,
            'dependencia' => $data['dependencia'] ?? null,
            'vigencia' => $data['vigencia'] ?? null,
            'proyecto_titulo_override' => $data['proyecto_titulo_override'] ?? null,
            'pilar' => $data['pilar'] ?? null,
            'eje' => $data['eje'] ?? null,
            'linea' => $data['linea'] ?? null,
            'programa' => $data['programa'] ?? null,
            'subprograma' => $data['subprograma'] ?? null,
            'codigo_fuente' => $data['codigo_fuente'] ?? null,
            'nombre_fuente' => $data['nombre_fuente'] ?? null,
            'meta_plan_codigo' => $data['meta_plan_codigo'] ?? null,
            'meta_plan_nombre' => $data['meta_plan_nombre'] ?? null,
            'municipio_relacion' => $this->municipioNames($this->municipioIds),
            'beneficiarios' => $data['beneficiarios'] ?? null,
            'observaciones' => $data['observaciones'] ?? null,
        ];
        $data['municipio'] = $this->municipioNames($this->municipioIds);
        unset(
            $data['sector_principal_id'],
            $data['sectores_secundarios'],
            $data['municipio_ids'],
            $data['horizonte_anio_0'],
            $data['horizonte_anio_1'],
            $data['horizonte_anio_2'],
            $data['horizonte_anio_3'],
            $data['tipo_presentacion'],
            $data['tipo_tramite'],
            $data['codigo_dependencia'],
            $data['dependencia'],
            $data['vigencia'],
            $data['proyecto_titulo_override'],
            $data['pilar'],
            $data['eje'],
            $data['linea'],
            $data['programa'],
            $data['subprograma'],
            $data['codigo_fuente'],
            $data['nombre_fuente'],
            $data['meta_plan_codigo'],
            $data['meta_plan_nombre'],
            $data['beneficiarios'],
            $data['observaciones'],
            $data['sector_texto_plantilla']
        );
        unset($data['drive_setup_mode']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->syncSectors();
        $this->record->municipios()->sync($this->municipioIds);
        /** @var ProjectBankExcelService $service */
        $service = app(ProjectBankExcelService::class);
        $service->ensureSeeded($this->record);
        $service->saveProfile($this->record, $this->bankProfileData);

        if ($this->driveSetupWarning) {
            Notification::make()
                ->title('Proyecto creado con observación de Drive')
                ->body($this->driveSetupWarning)
                ->warning()
                ->send();
        }
    }


    private function validateProductBelongsToPrimarySector(array $data): void
    {
        $productId = (int) ($data['producto_id'] ?? 0);
        $primaryId = (int) ($data['sector_principal_id'] ?? 0);
        if ($productId <= 0 || $primaryId <= 0) {
            return;
        }

        $belongs = \App\Models\Producto::query()
            ->where('id', $productId)
            ->where('sector_id', $primaryId)
            ->exists();

        if (! $belongs) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'producto_id' => 'El Producto MGA seleccionado no pertenece al sector principal.',
            ]);
        }
    }

    private function syncSectors(): void
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

    private function municipioNames(array $ids): string
    {
        if (empty($ids)) {
            return '';
        }

        return Municipio::query()
            ->whereIn('id', $ids)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->implode(', ');
    }

    private function createDriveStructureForProject(array $data): array
    {
        /** @var GoogleDriveService $drive */
        $drive = app(GoogleDriveService::class);
        $userId = auth()->id();

        $projectFolderName = trim((string) ($data['id_proyecto'] ?? '')) . ' - ' . trim((string) ($data['nombre'] ?? ''));
        if (!$drive->isAuthorized($userId)) {
            $data['drive_folder_id'] = null;
            $data['ruta_drive'] = null;
            $this->driveSetupWarning = 'No se creó la carpeta automática porque no hay conexión activa con Drive. Puedes conectarlo luego y sincronizar.';
            return $data;
        }

        try {
            $created = $drive->createProjectBaseStructure(trim($projectFolderName, ' -'), $userId);
        } catch (\Throwable $e) {
            $data['drive_folder_id'] = null;
            $data['ruta_drive'] = null;
            $this->driveSetupWarning = 'No se pudo crear la estructura base en Drive: ' . $e->getMessage() . '. El proyecto se creó sin carpeta vinculada.';
            return $data;
        }

        $data['drive_folder_id'] = (string) ($created['id'] ?? '');
        $data['ruta_drive'] = (string) ($created['url'] ?? '');

        return $data;
    }
}
