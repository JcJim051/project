<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Http\Controllers\ChecklistController;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\View\View;

class ManageProjectChecklist extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.manage-project-checklist';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        /** @var ChecklistController $controller */
        $controller = app(ChecklistController::class);
        $response = $controller->show($this->record);

        if ($response instanceof View) {
            $data = $response->getData();
            $this->viewData = [
                'project' => $data['project'] ?? null,
                'requirements' => $data['requirements'] ?? collect(),
                'applied' => $data['applied'] ?? [],
                'totalsByFolder' => $data['totalsByFolder'] ?? collect(),
                'sectorCatalog' => $data['sectorCatalog'] ?? ['ordered' => [], 'names' => []],
            ];
        }
    }

    protected function getViewData(): array
    {
        return $this->viewData;
    }

    public function getHeading(): string
    {
        $name = $this->record?->nombre_clave ?: $this->record?->nombre ?: 'Proyecto';

        return 'Checklist: ' . $name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('gestionar')
                ->label('Ir a Gestionar')
                ->icon('heroicon-o-folder')
                ->url(ProjectResource::getUrl('manage', ['record' => $this->record])),
        ];
    }
}
