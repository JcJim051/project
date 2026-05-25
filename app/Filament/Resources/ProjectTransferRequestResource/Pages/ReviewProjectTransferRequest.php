<?php

namespace App\Filament\Resources\ProjectTransferRequestResource\Pages;

use App\Filament\Resources\ProjectTransferRequestResource;
use App\Models\ProjectTransferRequest;
use App\Models\RequirementEvidence;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ReviewProjectTransferRequest extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectTransferRequestResource::class;

    protected static string $view = 'filament.resources.project-transfer-request-resource.pages.review-project-transfer-request';

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
        $this->record->load(['project.sectores', 'requestedBy', 'decidedBy']);

        abort_unless(auth()->user()?->canAuthorizeMgaTransfer(), 403);
    }

    public function getHeading(): string
    {
        return 'Ficha previa de revisión interna (MGA)';
    }

    protected function getViewData(): array
    {
        $project = $this->record->project;
        $requirements = $project ? $this->getActiveRequirementsForProject($project) : collect();

        $evidenceByRequirement = RequirementEvidence::query()
            ->where('project_id', $project?->id)
            ->whereIn('requirement_id', $requirements->pluck('id'))
            ->where('in_drive', true)
            ->get()
            ->groupBy('requirement_id');

        $comments = $this->record->requirementComments()
            ->with('author:id,name')
            ->get()
            ->keyBy('requirement_id');
        $previousComments = $this->loadPreviousComments($this->record);

        $groupedPayload = $this->buildReviewSections($requirements, $evidenceByRequirement, $comments, $previousComments);

        return [
            'transferRequest' => $this->record,
            'project' => $project,
            'reviewGroups' => $groupedPayload,
        ];
    }

    private function buildReviewSections($requirements, $evidenceByRequirement, $comments, array $previousComments = []): array
    {
        $folders = collect($requirements)->groupBy(fn ($req) => (string) ($req->carpeta ?: 'Sin carpeta'));
        $top = [];

        foreach ($folders as $folderName => $items) {
            $sample = $items->first();
            $code = $this->detectTopGroupCode((string) $folderName)
                ?? $this->detectTopGroupCode((string) ($sample->numeracion ?? ''))
                ?? $this->detectTopGroupCode((string) ($sample->codigo_interno ?? ''))
                ?? '99';
            if (!in_array($code, ['01', '02', '03', '04', '05'], true)) {
                continue;
            }

            $done = $items->filter(fn ($req) => ($evidenceByRequirement[$req->id] ?? collect())->isNotEmpty())->count();
            $folderPayload = [
                'name' => $this->stripFolderPrefix((string) $folderName),
                'progress' => $done . ' / ' . $items->count(),
                'items' => $items->map(function ($req) use ($evidenceByRequirement, $comments, $previousComments) {
                    $currentRow = $comments[$req->id] ?? null;
                    $currentComment = (string) ($currentRow?->comment ?? '');
                    $currentAuthor = (string) ($currentRow?->author?->name ?? '');
                    $currentDate = optional($currentRow?->updated_at)->format('Y-m-d H:i') ?? '';
                    $fallbackPrev = $this->resolvePreviousCommentForRequirement($req, $previousComments);
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
                        'comment' => $currentComment,
                        'previous_comment' => (string) (($previousComments['by_requirement_id'][$req->id]['comment'] ?? '') !== ''
                            ? $previousComments['by_requirement_id'][$req->id]['comment']
                            : ($fallbackPrev['comment'] ?? $currentComment)),
                        'previous_author' => (string) (($previousComments['by_requirement_id'][$req->id]['author'] ?? '') !== ''
                            ? $previousComments['by_requirement_id'][$req->id]['author']
                            : ($fallbackPrev['author'] ?? $currentAuthor)),
                        'previous_date' => (string) (($previousComments['by_requirement_id'][$req->id]['date'] ?? '') !== ''
                            ? $previousComments['by_requirement_id'][$req->id]['date']
                            : ($fallbackPrev['date'] ?? $currentDate)),
                    ];
                })->values()->all(),
            ];

            if (!isset($top[$code])) {
                $top[$code] = [
                    'code' => $code,
                    'label' => $this->groupLabels[$code] ?? ('Grupo ' . $code),
                    'folders' => [],
                ];
            }
            $top[$code]['folders'][] = $folderPayload;
        }

        $ordered = [];
        foreach (['01', '02', '03', '04', '05'] as $code) {
            if (!isset($top[$code])) {
                continue;
            }
            $ordered[] = $top[$code];
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

    private function detectTopGroupCode(string $folder): ?string
    {
        if (preg_match('/^\s*(\d{1,2})\b/u', $folder, $m)) {
            return str_pad((string) ((int) $m[1]), 2, '0', STR_PAD_LEFT);
        }

        $normalized = strtolower(\Illuminate\Support\Str::ascii($folder));
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

    private function loadPreviousComments(ProjectTransferRequest $current): array
    {
        $requestIds = ProjectTransferRequest::query()
            ->where('project_id', $current->project_id)
            ->where('id', '<=', $current->id)
            ->orderByDesc('id')
            ->pluck('id');

        if ($requestIds->isEmpty()) {
            return [];
        }

        $rows = \App\Models\ProjectTransferRequestRequirementComment::query()
            ->with('author:id,name')
            ->whereIn('project_transfer_request_id', $requestIds->all())
            ->orderByDesc('project_transfer_request_id')
            ->orderByDesc('id')
            ->get();

        $latestByRequirement = [];
        $latestByKey = [];
        foreach ($rows as $row) {
            $rid = (int) $row->requirement_id;
            if (isset($latestByRequirement[$rid])) {
                // continue to key-map fallback evaluation
            } else {
                $latestByRequirement[$rid] = [
                    'comment' => (string) $row->comment,
                    'author' => (string) ($row->author?->name ?? ''),
                    'date' => optional($row->updated_at)->format('Y-m-d H:i') ?? '',
                ];
            }

            $req = \App\Models\Requirement::find($rid);
            if ($req) {
                $key = $this->commentKey((string) $req->carpeta, (string) ($req->nombre_documento ?: $req->requisito));
                if ($key !== '' && !isset($latestByKey[$key])) {
                    $latestByKey[$key] = [
                        'comment' => (string) $row->comment,
                        'author' => (string) ($row->author?->name ?? ''),
                        'date' => optional($row->updated_at)->format('Y-m-d H:i') ?? '',
                    ];
                }
            }
        }

        return [
            'by_requirement_id' => $latestByRequirement,
            'by_key' => $latestByKey,
        ];
    }

    private function resolvePreviousCommentForRequirement($req, array $previousComments): array
    {
        $key = $this->commentKey((string) ($req->carpeta ?? ''), (string) ($req->nombre_documento ?: $req->requisito));
        if ($key !== '' && isset($previousComments['by_key'][$key])) {
            return $previousComments['by_key'][$key];
        }
        return [];
    }

    private function commentKey(string $folder, string $title): string
    {
        $folder = mb_strtolower(trim(\Illuminate\Support\Str::ascii($folder)));
        $title = mb_strtolower(trim(\Illuminate\Support\Str::ascii($title)));
        if ($title === '') {
            return '';
        }
        return $folder . '|' . $title;
    }
}
