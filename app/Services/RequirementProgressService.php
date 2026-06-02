<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RequirementProgressService
{
    private const COMPOSITE_BUDGET_FOLDERS = [
        '2.1' => '2.1 Presupuesto',
        '2.4' => '2.4 Estudio de Mercado',
        '2.6' => '2.6 Programación',
    ];

    public function analyze(Collection $requirements, Collection $evidences): array
    {
        $requirements = $requirements->values();
        $evidenceByRequirement = $evidences->groupBy('requirement_id');
        $requirementsByFolder = $requirements->groupBy(fn ($req) => $this->normalize((string) ($req->carpeta ?? '')));

        $statuses = [];
        foreach ($requirements as $requirement) {
            $directCount = $this->validEvidenceCount($requirement->id, $evidenceByRequirement);
            $statuses[$requirement->id] = [
                'id' => (int) $requirement->id,
                'has_evidence' => $directCount > 0,
                'valid_evidence_count' => $directCount,
                'fulfillment_source' => $this->directFulfillmentSource($requirement->id, $evidenceByRequirement),
                'is_composite_parent' => false,
                'composite_folder' => null,
                'composite_done' => 0,
                'composite_total' => 0,
                'count_in_progress' => true,
            ];
        }

        foreach ($requirements as $requirement) {
            if (!$this->isCompositeParent($requirement)) {
                continue;
            }

            $targetFolder = $this->compositeTargetFolder($requirement);
            $children = $requirementsByFolder
                ->get($this->normalize((string) $targetFolder), collect())
                ->reject(fn ($child) => (int) $child->id === (int) $requirement->id)
                ->values();

            $total = $children->count();
            $done = $children->filter(fn ($child) => (bool) ($statuses[$child->id]['has_evidence'] ?? false))->count();
            $complete = $total > 0 && $done === $total;

            $statuses[$requirement->id] = [
                'id' => (int) $requirement->id,
                'has_evidence' => $complete,
                'valid_evidence_count' => $done,
                'fulfillment_source' => 'rollup',
                'is_composite_parent' => true,
                'composite_folder' => $targetFolder,
                'composite_done' => $done,
                'composite_total' => $total,
                // The parent is a visual roll-up; children carry the real denominator.
                'count_in_progress' => false,
            ];
        }

        return ['requirements' => $statuses];
    }

    public function buildOverallProgress(Collection $requirements, array $analysis): array
    {
        $statuses = $analysis['requirements'] ?? [];
        $countable = $requirements->filter(fn ($req) => (bool) ($statuses[$req->id]['count_in_progress'] ?? true))->values();
        $total = $countable->count();
        $done = $countable->filter(fn ($req) => (bool) ($statuses[$req->id]['has_evidence'] ?? false))->count();

        return [
            'total' => $total,
            'done' => $done,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    public function buildFolderProgress(Collection $requirements, array $analysis): array
    {
        $statuses = $analysis['requirements'] ?? [];
        $folderProgress = [];

        foreach ($requirements->groupBy(fn ($req) => $req->carpeta ?? 'Sin carpeta') as $folder => $items) {
            $total = $items->count();
            $done = $items->filter(fn ($req) => (bool) ($statuses[$req->id]['has_evidence'] ?? false))->count();
            $folderProgress[$folder] = [
                'total' => $total,
                'done' => $done,
                'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
            ];
        }

        return $folderProgress;
    }

    public function buildTopGroupProgress(Collection $requirements, array $analysis, callable $detectTopGroupCode): array
    {
        $labels = [
            '01' => 'Formulacion',
            '02' => 'Presupuesto',
            '03' => 'Certificaciones',
            '04' => 'Licencias y Permisos',
            '05' => 'Estudios y Disenos',
        ];

        $summary = [];
        foreach ($labels as $code => $label) {
            $summary[$code] = [
                'code' => $code,
                'label' => $label,
                'total' => 0,
                'done' => 0,
                'percent' => 0,
            ];
        }

        $statuses = $analysis['requirements'] ?? [];
        foreach ($requirements as $requirement) {
            if (!(bool) ($statuses[$requirement->id]['count_in_progress'] ?? true)) {
                continue;
            }

            $groupCode = $detectTopGroupCode((string) ($requirement->carpeta ?? ''));
            if (!$groupCode || !isset($summary[$groupCode])) {
                continue;
            }

            $summary[$groupCode]['total']++;
            if ((bool) ($statuses[$requirement->id]['has_evidence'] ?? false)) {
                $summary[$groupCode]['done']++;
            }
        }

        foreach ($summary as $code => $item) {
            $summary[$code]['percent'] = $item['total'] > 0
                ? (int) round(($item['done'] / $item['total']) * 100)
                : 0;
        }

        return $summary;
    }

    public function isCompositeParent($requirement): bool
    {
        $folder = $this->normalize((string) ($requirement->carpeta ?? ''));
        if ($folder !== '02 presupuesto') {
            return false;
        }

        return $this->compositeCode($requirement) !== null;
    }

    public function compositeTargetFolder($requirement): ?string
    {
        $code = $this->compositeCode($requirement);
        return $code ? self::COMPOSITE_BUDGET_FOLDERS[$code] : null;
    }

    private function compositeCode($requirement): ?string
    {
        $code = null;
        foreach ([$requirement->codigo_interno ?? null, $requirement->numeracion ?? null] as $field) {
            $value = $this->normalize((string) $field);
            if (preg_match('/^2\.(?:1|4|6)$/', $value)) {
                $code = $value;
                break;
            }
        }

        if (!$code) {
            foreach ([$requirement->nombre_documento ?? null, $requirement->requisito ?? null, $requirement->texto ?? null] as $field) {
                $value = $this->normalize((string) $field);
                if (preg_match('/(?:^|\s)(2\.(?:1|4|6))(?:\s|$)/', $value, $matches)) {
                    $code = $matches[1];
                    break;
                }
            }
        }

        if (!$code) {
            return null;
        }

        $label = $this->normalize(trim(implode(' ', [
            (string) ($requirement->nombre_documento ?? ''),
            (string) ($requirement->requisito ?? ''),
            (string) ($requirement->texto ?? ''),
        ])));

        // Only the PDF parent rolls up. Sibling rows such as "Presupuesto Excel"
        // or "Programacion Project" remain directly uploadable.
        if (str_contains($label, 'excel') || str_contains($label, 'project') || str_contains($label, 'cotizacion')) {
            return null;
        }

        return $code;
    }

    private function validEvidenceCount(int $requirementId, Collection $evidenceByRequirement): int
    {
        return $evidenceByRequirement
            ->get($requirementId, collect())
            ->filter(fn ($evidence) => (bool) ($evidence->in_drive ?? false))
            ->unique(fn ($evidence) => $evidence->drive_file_id ?: mb_strtolower((string) ($evidence->drive_file_name ?? '')))
            ->count();
    }

    private function directFulfillmentSource(int $requirementId, Collection $evidenceByRequirement): string
    {
        $sources = $evidenceByRequirement
            ->get($requirementId, collect())
            ->filter(fn ($evidence) => (bool) ($evidence->in_drive ?? false))
            ->pluck('source')
            ->map(fn ($source) => mb_strtolower((string) $source))
            ->filter()
            ->values();

        if ($sources->contains('manual_link')) {
            return 'manual';
        }
        if ($sources->contains('auto_match') || $sources->contains('drive')) {
            return 'auto';
        }
        if ($sources->contains('upload')) {
            return 'upload';
        }

        return 'none';
    }

    private function normalize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $value = Str::ascii($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9.]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}
