<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Http\Controllers\ProjectManageController;
use App\Services\GoogleDriveService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ManageProject extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.manage-project';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        /** @var ProjectManageController $controller */
        $controller = app(ProjectManageController::class);
        /** @var GoogleDriveService $drive */
        $drive = app(GoogleDriveService::class);

        $response = $controller->showLegacy(request(), $this->record, $drive);

        if ($response instanceof RedirectResponse) {
            $this->redirect($response->getTargetUrl(), navigate: false);
            return;
        }

        if ($response instanceof View) {
            $data = $response->getData();
            $this->viewData = [
                'project' => $data['project'] ?? null,
                'requirements' => $data['requirements'] ?? null,
                'requirementsByFolder' => $data['requirementsByFolder'] ?? null,
                'evidences' => $data['evidences'] ?? null,
                'driveConnected' => $data['driveConnected'] ?? null,
                'driveReady' => $data['driveReady'] ?? null,
                'syncReport' => $data['syncReport'] ?? null,
                'renumerated' => $data['renumerated'] ?? null,
                'overallPercent' => $data['overallPercent'] ?? null,
                'folderProgress' => $data['folderProgress'] ?? null,
                'manageSections' => $data['manageSections'] ?? null,
                'topGroupProgress' => $data['topGroupProgress'] ?? null,
                'attachmentRuns' => $data['attachmentRuns'] ?? null,
                'attachmentPdfHealth' => $data['attachmentPdfHealth'] ?? null,
                'canGenerateAttachmentPackage' => $data['canGenerateAttachmentPackage'] ?? null,
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

        return 'Gestionar: ' . $name;
    }

    public function getManageUrl(): string
    {
        return route('projects.manage.legacy', $this->record);
    }
}
