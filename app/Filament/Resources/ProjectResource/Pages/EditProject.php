<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\Municipio;
use App\Services\ProjectBankExcelService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;
    protected int $primarySectorId = 0;
    protected array $secondarySectorIds = [];
    protected array $municipioIds = [];
    protected array $bankProfileData = [];

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

        return $data;
    }

    protected function afterSave(): void
    {
        $this->syncSectors();
        $this->record->municipios()->sync($this->municipioIds);
        /** @var ProjectBankExcelService $service */
        $service = app(ProjectBankExcelService::class);
        $service->ensureSeeded($this->record);
        $service->saveProfile($this->record, $this->bankProfileData);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->loadMissing(['sectores', 'municipios']);
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
        $data['municipio_ids'] = $this->record->municipios->pluck('id')->values()->all();

        if (empty($data['municipio_ids']) && !empty($this->record->municipio)) {
            $names = collect(explode(',', (string) $this->record->municipio))
                ->map(fn ($name) => trim($name))
                ->filter()
                ->all();
            $data['municipio_ids'] = Municipio::query()
                ->whereIn('nombre', $names)
                ->pluck('id')
                ->values()
                ->all();
        }

        /** @var ProjectBankExcelService $service */
        $service = app(ProjectBankExcelService::class);
        $service->ensureSeeded($this->record);
        $profile = $service->profileFor($this->record);
        $data['horizonte_anio_0'] = $profile->horizonte_anio_0;
        $data['horizonte_anio_1'] = $profile->horizonte_anio_1;
        $data['horizonte_anio_2'] = $profile->horizonte_anio_2;
        $data['horizonte_anio_3'] = $profile->horizonte_anio_3;
        $data['tipo_presentacion'] = $profile->tipo_presentacion;
        $data['tipo_tramite'] = $profile->tipo_tramite;
        $data['codigo_dependencia'] = $profile->codigo_dependencia;
        $data['dependencia'] = $profile->dependencia;
        $data['vigencia'] = $profile->vigencia;
        $data['proyecto_titulo_override'] = $profile->proyecto_titulo_override;
        $data['sector_texto_plantilla'] = $profile->sector_texto_plantilla;
        $data['pilar'] = $profile->pilar;
        $data['eje'] = $profile->eje;
        $data['linea'] = $profile->linea;
        $data['programa'] = $profile->programa;
        $data['subprograma'] = $profile->subprograma;
        $data['codigo_fuente'] = $profile->codigo_fuente;
        $data['nombre_fuente'] = $profile->nombre_fuente;
        $data['meta_plan_codigo'] = $profile->meta_plan_codigo;
        $data['meta_plan_nombre'] = $profile->meta_plan_nombre;
        $data['beneficiarios'] = $profile->beneficiarios;
        $data['observaciones'] = $profile->observaciones;

        return $data;
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
}
