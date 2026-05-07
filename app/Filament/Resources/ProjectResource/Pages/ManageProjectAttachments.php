<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\AttachmentPackageRun;
use App\Models\Project;
use App\Models\RequirementEvidence;
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
        $attachmentRuns = AttachmentPackageRun::query()
            ->where('project_id', $project->id)
            ->latest('id')
            ->limit(20)
            ->get();

        $this->viewData = [
            'project' => $project,
            'overallPercent' => $overallPercent,
            'canGenerateAttachmentPackage' => $overallPercent === 100,
            'attachmentRuns' => $attachmentRuns,
            'attachmentPdfHealth' => $this->buildAttachmentPdfHealth(),
            'hasActiveRuns' => $attachmentRuns->contains(fn (AttachmentPackageRun $run) => in_array($run->status, ['pending', 'running'], true)),
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
        $requirements = $project->requisitos()->where('requirements.visible', true)->get(['requirements.id', 'requirements.carpeta', 'requirements.sector']);
        $requirements = $this->filterSectorial($requirements, $project);

        $total = $requirements->count();
        if ($total === 0) {
            return 0;
        }

        $done = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->where('in_drive', true)
            ->distinct('requirement_id')
            ->count('requirement_id');

        return (int) round(($done / $total) * 100);
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
        $pythonBin = (string) config('services.attachments_pdf.python_bin', 'python3');
        $scriptPath = (string) config('services.attachments_pdf.script_path', base_path('scripts/generate_attachment_pdfs.py'));

        $pythonOk = false;
        $pythonVersion = null;
        $pythonError = null;

        try {
            $process = new Process([$pythonBin, '--version']);
            $process->setTimeout(10);
            $process->run();

            if ($process->isSuccessful()) {
                $pythonOk = true;
                $pythonVersion = trim($process->getOutput() ?: $process->getErrorOutput());
            } else {
                $pythonError = trim($process->getErrorOutput() ?: $process->getOutput());
            }
        } catch (\Throwable $e) {
            $pythonError = $e->getMessage();
        }

        return [
            'python_bin' => $pythonBin,
            'script_path' => $scriptPath,
            'script_exists' => is_file($scriptPath),
            'python_ok' => $pythonOk,
            'python_version' => $pythonVersion,
            'python_error' => $pythonError,
        ];
    }
}
