<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\PlanDevelopmentCatalogItem;
use App\Services\ProjectBankExcelService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageProjectBank extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.manage-project-bank';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        /** @var ProjectBankExcelService $service */
        $service = app(ProjectBankExcelService::class);
        $service->ensureSeeded($this->record);

        $this->record->loadMissing(['producto', 'sectores', 'municipios', 'formulador', 'estructurador']);

        $this->viewData = [
            'project' => $this->record,
            'profile' => $service->profileFor($this->record),
            'signatories' => $service->signatoriesFor($this->record),
            'financingRows' => $service->financingRowsFor($this->record),
            'activityRows' => $service->activityRowsFor($this->record),
            'missingRequired' => $service->missingRequiredFields($this->record),
            'planCatalog' => PlanDevelopmentCatalogItem::query()
                ->where('activo', true)
                ->orderBy('pilar_codigo')
                ->orderBy('eje_codigo')
                ->orderBy('linea_codigo')
                ->orderBy('programa_codigo')
                ->orderBy('subprograma_codigo')
                ->orderBy('codigo_meta_plan')
                ->get()
                ->toArray(),
        ];
    }

    protected function getViewData(): array
    {
        return $this->viewData;
    }

    public function getHeading(): string
    {
        $name = $this->record?->nombre ?: 'Proyecto';

        return 'Banco Excel: ' . $name;
    }
}
