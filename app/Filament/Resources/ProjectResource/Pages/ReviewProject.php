<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\RequirementEvidence;
use App\Services\RequirementProgressService;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Str;

class ReviewProject extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected static string $view = 'filament.resources.project-resource.pages.review-project';

    public array $groupLabels = [
        '01' => '01 Formulación',
        '02' => '02 Presupuesto',
        '03' => '03 Certificaciones',
        '04' => '04 Licencias y Permisos',
        '05' => '05 Estudios y Diseños',
        '99' => 'Otros',
    ];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        abort_unless(auth()->user()?->canAccessPanel(), 403);
    }

    public function getHeading(): string
    {
        return 'Revisión documental';
    }

    protected function getViewData(): array
    {
        $project = $this->record;
        $requirements = $this->getActiveRequirementsForProject($project);

        $evidenceRows = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->where('in_drive', true)
            ->get();
        $evidenceByRequirement = $evidenceRows->groupBy('requirement_id');

        /** @var RequirementProgressService $progressService */
        $progressService = app(RequirementProgressService::class);
        $progressAnalysis = $progressService->analyze($requirements, $evidenceRows);

        return [
            'project' => $project,
            'reviewGroups' => $this->buildSections($requirements, $evidenceByRequirement, $progressAnalysis),
        ];
    }

    private function buildSections($requirements, $evidenceByRequirement, array $progressAnalysis): array
    {
        $folders = collect($requirements)->groupBy(fn ($req) => (string) ($req->carpeta ?: 'Sin carpeta'));
        $top = [];

        foreach ($folders as $folderName => $items) {
            $sample = $items->first();
            $code = $this->detectTopGroupCode((string) $folderName)
                ?? $this->detectTopGroupCode((string) ($sample->numeracion ?? ''))
                ?? $this->detectTopGroupCode((string) ($sample->codigo_interno ?? ''))
                ?? '99';

            if (!isset($top[$code])) {
                $top[$code] = [
                    'code' => $code,
                    'label' => $this->groupLabels[$code] ?? ('Grupo ' . $code),
                    'folders' => [],
                ];
            }

            $statuses = $progressAnalysis['requirements'] ?? [];
            $done = $items->filter(fn ($req) => (bool) ($statuses[$req->id]['has_evidence'] ?? false))->count();

            $top[$code]['folders'][] = [
                'name' => $this->stripFolderPrefix((string) $folderName),
                'progress' => $done . ' / ' . $items->count(),
                'items' => $items->map(function ($req) use ($evidenceByRequirement, $statuses) {
                    $status = $statuses[$req->id] ?? [];
                    $evidences = ($evidenceByRequirement[$req->id] ?? collect())->map(function ($ev) {
                        return [
                            'id' => (int) $ev->id,
                            'name' => (string) ($ev->drive_file_name ?: 'Archivo'),
                            'preview_url' => $ev->drive_file_id ? 'https://drive.google.com/file/d/' . $ev->drive_file_id . '/preview' : null,
                            'view_url' => $ev->drive_file_id ? 'https://drive.google.com/file/d/' . $ev->drive_file_id . '/view' : null,
                        ];
                    })->values()->all();

                    return [
                        'id' => (int) $req->id,
                        'title' => trim((string) ($req->nombre_documento ?: $req->requisito)),
                        'folder' => (string) ($req->carpeta ?: 'Sin carpeta'),
                        'evidences' => $evidences,
                        'is_composite_parent' => (bool) ($status['is_composite_parent'] ?? false),
                        'composite_folder' => $status['composite_folder'] ?? null,
                        'composite_done' => (int) ($status['composite_done'] ?? 0),
                        'composite_total' => (int) ($status['composite_total'] ?? 0),
                    ];
                })->values()->all(),
            ];
        }

        $ordered = [];
        foreach (['01', '02', '03', '04', '05', '99'] as $code) {
            if (isset($top[$code])) {
                $ordered[] = $top[$code];
            }
        }

        return $ordered;
    }

    private function getActiveRequirementsForProject($project)
    {
        $requirements = $project->requisitos()
            ->where('requirements.visible', true)
            ->orderBy('carpeta')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->get();

        return $this->filterSectorial($requirements, $project);
    }

    private function filterSectorial($requirements, $project)
    {
        $project->loadMissing('sectores');

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

        $value = Str::ascii($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim((string) $value);
    }

    private function detectTopGroupCode(string $folder): ?string
    {
        if (preg_match('/^\s*(\d{1,2})\b/u', $folder, $m)) {
            return str_pad((string) ((int) $m[1]), 2, '0', STR_PAD_LEFT);
        }

        $normalized = strtolower(Str::ascii($folder));
        if (str_contains($normalized, 'formulacion')) return '01';
        if (str_contains($normalized, 'presupuesto')) return '02';
        if (str_contains($normalized, 'certificacion')) return '03';
        if (str_contains($normalized, 'licencias') || str_contains($normalized, 'permisos')) return '04';
        if (str_contains($normalized, 'estudios') || str_contains($normalized, 'disenos')) return '05';

        return null;
    }

    private function stripFolderPrefix(string $name): string
    {
        $name = preg_replace('/^\s*\d{1,2}(?:\.\d+)?\s*/u', '', $name) ?? $name;

        return trim($name);
    }
}
