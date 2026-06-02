<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Http\Controllers\ProjectDocumentController;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageProjectDocuments extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.manage-project-documents';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        /** @var ProjectDocumentController $controller */
        $controller = app(ProjectDocumentController::class);

        $this->viewData = [
            'project' => $this->record,
            'templates' => $controller->allowedTemplates($this->record),
        ];
    }

    protected function getViewData(): array
    {
        return $this->viewData;
    }

    public function getHeading(): string
    {
        $name = $this->record?->nombre_clave ?: $this->record?->nombre ?: 'Proyecto';

        return 'Crear certificaciones: ' . $name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver_manage')
                ->label('Volver a gestionar')
                ->icon('heroicon-o-arrow-left')
                ->url(ProjectResource::getUrl('manage', ['record' => $this->record])),
        ];
    }
}
