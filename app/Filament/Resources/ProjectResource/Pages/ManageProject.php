<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Http\Controllers\ProjectManageController;
use App\Services\GoogleDriveService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\HtmlString;
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
                'overallProgress' => $data['overallProgress'] ?? ['done' => 0, 'total' => 0, 'percent' => 0],
                'folderProgress' => $data['folderProgress'] ?? null,
                'manageSections' => $data['manageSections'] ?? null,
                'topGroupProgress' => $data['topGroupProgress'] ?? null,
                'progressAnalysis' => $data['progressAnalysis'] ?? null,
                'attachmentRuns' => $data['attachmentRuns'] ?? null,
                'attachmentPdfHealth' => $data['attachmentPdfHealth'] ?? null,
                'attachmentsMinPercent' => $data['attachmentsMinPercent'] ?? 80,
                'canGenerateAttachmentPackage' => $data['canGenerateAttachmentPackage'] ?? null,
                'transferRequest' => $data['transferRequest'] ?? null,
                'canTransferToMga' => $data['canTransferToMga'] ?? false,
                'canRequestTransfer' => $data['canRequestTransfer'] ?? false,
                'canAuthorizeTransfer' => $data['canAuthorizeTransfer'] ?? false,
                'canAcknowledgeTransfer' => $data['canAcknowledgeTransfer'] ?? false,
                'transferReceiptStates' => $data['transferReceiptStates'] ?? [],
                'workflowStages' => $data['workflowStages'] ?? collect(),
                'canValidateWorkflow' => $data['canValidateWorkflow'] ?? false,
                'canOverrideWorkflowApplicability' => $data['canOverrideWorkflowApplicability'] ?? false,
                'mgaUrl' => $this->getMgaUrl(),
            ];
        }
    }

    protected function getViewData(): array
    {
        return $this->viewData;
    }

    public function getHeader(): ?View
    {
        return view(
            'filament.resources.project-resource.pages.partials.manage-project-header',
            array_merge($this->viewData, [
                'breadcrumbs' => filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [],
                'heading' => $this->getHeading(),
            ]),
        );
    }

    public function getHeading(): string|HtmlString
    {
        $name = $this->record?->nombre_clave ?: $this->record?->nombre ?: 'Proyecto';

        return 'Gestionar: '.e($name);
    }

    public function getManageUrl(): string
    {
        return route('projects.manage.legacy', $this->record);
    }

    private function getMgaUrl(): ?string
    {
        $projectId = trim((string) ($this->record->id_proyecto ?? ''));
        if ($projectId === '') {
            return null;
        }

        // Accept multiple possible attribute names for compatibility.
        $alternativeId = trim((string) (
            $this->record->mga_alternative_id
            ?? $this->record->alternative_id
            ?? $this->record->ap35
            ?? ''
        ));

        if ($alternativeId !== '') {
            return 'https://mgaweb.dnp.gov.co/Preparation/PE05?ProjectId='
                .urlencode($projectId)
                .'&AlternativeId='
                .urlencode($alternativeId);
        }

        return 'https://mgaweb.dnp.gov.co/Identification/Id01?ProjectId='.urlencode($projectId);
    }
}
