<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Services\ProjectBankRequestService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageProjectBankRequest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.manage-project-bank-request';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->viewData = [
            'bankPageMode' => 'request',
            'project' => $this->record,
            'bankRequests' => $this->record->bankRequests()
                ->with(['createdBy', 'template'])
                ->latest('id')
                ->get(),
            'bankRequestTemplate' => app(ProjectBankRequestService::class)->activeTemplate(),
        ];
    }

    protected function getViewData(): array
    {
        return $this->viewData;
    }

    public function getHeading(): string
    {
        $name = $this->record?->nombre ?: 'Proyecto';

        return 'Solicitud al Banco de Programas y Proyectos: '.$name;
    }
}
