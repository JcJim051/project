<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\AttachmentPackageRun;
use App\Models\Project;
use App\Models\RequirementEvidence;
use App\Services\AttachmentPackageService;
use App\Services\AttachmentPdfRuntime;
use App\Services\RequirementProgressService;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Symfony\Component\Process\Process;

class ManageProjectAttachments extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.manage-project-attachments';

    protected array $viewData = [];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        /** @var Project $project */
        $project = $this->record;

        $this->expireStaleRuns($project->id);

        $overallPercent = $this->overallPercent($project);
        $attachmentsMinPercent = max(1, min(100, (int) ($project->attachments_min_percent ?? 80)));
        $attachmentRuns = AttachmentPackageRun::query()
            ->where('project_id', $project->id)
            ->latest('id')
            ->limit(20)
            ->get();
        $activeRun = $attachmentRuns->first(fn (AttachmentPackageRun $run) => in_array($run->status, ['pending', 'running'], true));
        $availableDocuments = app(AttachmentPackageService::class)->availablePackageDocuments($project);
        $availableKeys = collect($availableDocuments)->pluck('key')->all();
        $rememberedSelection = collect($project->attachment_package_selection ?: [])
            ->map(fn ($key) => (string) $key)
            ->filter(fn ($key) => in_array($key, $availableKeys, true))
            ->values()
            ->all();
        $selectedDocuments = !empty($rememberedSelection) ? $rememberedSelection : $availableKeys;

        $this->viewData = [
            'project' => $project,
            'overallPercent' => $overallPercent,
            'attachmentsMinPercent' => $attachmentsMinPercent,
            'canGenerateAttachmentPackage' => $overallPercent >= $attachmentsMinPercent,
            'attachmentRuns' => $attachmentRuns,
            'attachmentPdfHealth' => $this->buildAttachmentPdfHealth(),
            'availableAttachmentDocuments' => $availableDocuments,
            'selectedAttachmentDocuments' => $selectedDocuments,
            'hasActiveRuns' => (bool) $activeRun,
            'activeAttachmentRun' => $activeRun,
            'activeAttachmentRunUrl' => $activeRun ? route('projects.attachments.runs.show', [$project, $activeRun]) : null,
        ];
    }

    protected function getViewData(): array
    {
        return $this->viewData;
    }

    public function getHeading(): string
    {
        $name = $this->record?->nombre_clave ?: $this->record?->nombre ?: 'Proyecto';

        return 'Paquete PDF: ' . $name;
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

    private function overallPercent(Project $project): int
    {
        $project->loadMissing('sectores');
        $requirements = $project->requisitos()->where('requirements.visible', true)->get();
        $requirements = $this->filterSectorial($requirements, $project);
        $evidences = RequirementEvidence::query()->where('project_id', $project->id)->get();

        /** @var RequirementProgressService $progressService */
        $progressService = app(RequirementProgressService::class);
        $analysis = $progressService->analyze($requirements, $evidences);

        return $progressService->buildOverallProgress($requirements, $analysis)['percent'];
    }

    private function filterSectorial($requirements, Project $project)
    {
        $sectorNames = $project->sectores
            ->pluck('nombre')
            ->map(fn ($name) => $this->normalizeSector($name))
            ->filter()
            ->all();

        if (empty($sectorNames)) {
            return $requirements;
        }

        return $requirements->filter(function ($req) use ($sectorNames) {
            $carpeta = $this->normalizeSector($req->carpeta);
            if ($carpeta && str_contains($carpeta, 'documentos sectoriales')) {
                $reqSector = $this->normalizeSector($req->sector);
                if ($reqSector === '') {
                    return true;
                }

                return in_array($reqSector, $sectorNames, true);
            }

            return true;
        })->values();
    }

    private function normalizeSector(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = \Illuminate\Support\Str::ascii($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }

    private function expireStaleRuns(int $projectId): void
    {
        $threshold = now()->subMinutes(15);

        AttachmentPackageRun::query()
            ->where('project_id', $projectId)
            ->whereIn('status', ['pending', 'running'])
            ->where(function ($q) use ($threshold) {
                $q->where('updated_at', '<', $threshold)
                    ->orWhere(function ($inner) use ($threshold) {
                        $inner->whereNull('updated_at')
                            ->where('created_at', '<', $threshold);
                    });
            })
            ->update([
                'status' => 'failed',
                'error_message' => 'Proceso marcado como vencido por inactividad (15 min).',
                'finished_at' => now(),
            ]);
    }

    private function buildAttachmentPdfHealth(): array
    {
        return app(AttachmentPdfRuntime::class)->health();
    }
}
