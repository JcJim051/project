<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementEvidence;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;

class ProjectManageController extends Controller
{
    public function show(Request $request, Project $project, GoogleDriveService $drive)
    {
        $project->load(['requisitos', 'sectores']);
        $requirements = $project->requisitos()
            ->where('requirements.visible', true)
            ->orderBy('carpeta')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->get();

        $requirements = $this->filterSectorial($requirements, $project);

        $renumerated = $this->buildRenumerationMap($requirements);

        $driveConnected = $drive->isAuthorized(auth()->id());
        $driveReady = $driveConnected && $project->drive_folder_id;
        $syncReport = null;

        if ($driveReady && $request->boolean('sync')) {
            @ini_set('max_execution_time', '0');
            set_time_limit(0);
            try {
                RequirementEvidence::where('project_id', $project->id)->delete();
                $syncReport = $drive->syncProjectRequirements($project, $requirements, auth()->id());
            } catch (\Throwable $e) {
                return redirect()
                    ->route('projects.manage', $project)
                    ->withErrors(['archivo' => $e->getMessage() ?: 'Error al sincronizar con Drive.']);
            }
            if (!empty($syncReport['error'])) {
                return redirect()
                    ->route('projects.manage', $project)
                    ->withErrors(['archivo' => $syncReport['error']]);
            }
            if (!$request->boolean('debug')) {
                $syncReport = null;
            }
        }

        $evidences = RequirementEvidence::where('project_id', $project->id)
            ->get()
            ->groupBy('requirement_id');

        $requirementsByFolder = $requirements->groupBy(function ($req) {
            return $req->carpeta ?? 'Sin carpeta';
        });
        $manageSections = $this->buildManageSections($requirementsByFolder);

        $totalRequirements = $requirements->count();
        $completedRequirements = $requirements->filter(function ($req) use ($evidences) {
            $reqEvidences = $evidences[$req->id] ?? collect();
            return $reqEvidences->where('in_drive', true)->isNotEmpty();
        })->count();
        $overallPercent = $totalRequirements > 0 ? (int) round(($completedRequirements / $totalRequirements) * 100) : 0;

        $folderProgress = [];
        foreach ($requirementsByFolder as $folder => $items) {
            $folderTotal = $items->count();
            $folderDone = $items->filter(function ($req) use ($evidences) {
                $reqEvidences = $evidences[$req->id] ?? collect();
                return $reqEvidences->where('in_drive', true)->isNotEmpty();
            })->count();
            $folderPercent = $folderTotal > 0 ? (int) round(($folderDone / $folderTotal) * 100) : 0;
            $folderProgress[$folder] = [
                'total' => $folderTotal,
                'done' => $folderDone,
                'percent' => $folderPercent,
            ];
        }
        $topGroupProgress = $this->buildTopGroupProgress($folderProgress);

        return view('projects.manage', [
            'project' => $project,
            'requirements' => $requirements,
            'requirementsByFolder' => $requirementsByFolder,
            'evidences' => $evidences,
            'driveConnected' => $driveConnected,
            'driveReady' => $driveReady,
            'syncReport' => $syncReport,
            'renumerated' => $renumerated,
            'overallPercent' => $overallPercent,
            'folderProgress' => $folderProgress,
            'manageSections' => $manageSections,
            'topGroupProgress' => $topGroupProgress,
        ]);
    }

    public function upload(Request $request, Project $project, Requirement $requirement, GoogleDriveService $drive)
    {
        @ini_set('max_execution_time', '0');
        set_time_limit(0);
        $project->load('requisitos');

        if (!$project->requisitos->contains($requirement->id)) {
            return back()->withErrors(['archivo' => 'Este requisito no está marcado para el proyecto.']);
        }

        if (!$project->drive_folder_id) {
            return back()->withErrors(['archivo' => 'El proyecto no tiene carpeta de Drive configurada.']);
        }

        if (!$drive->isAuthorized(auth()->id())) {
            return back()->withErrors(['archivo' => 'Conecta Drive antes de cargar evidencias.']);
        }

        $data = $request->validate([
            'archivos' => ['required', 'array', 'min:1'],
            'archivos.*' => ['file', 'max:51200'],
        ], [
            'archivos.required' => 'Debes seleccionar al menos un archivo.',
            'archivos.*.max' => 'Cada archivo debe pesar máximo 50MB.',
        ]);

        $renumerated = $this->buildRenumerationMap($project->requisitos()->get());
        $prefix = $renumerated[$requirement->id] ?? $requirement->codigo_interno ?? $requirement->numeracion ?? '';
        $baseName = $requirement->nombre_documento ?: $requirement->requisito;

        $index = 1;
        $totalFiles = count($data['archivos']);
        foreach ($data['archivos'] as $archivo) {
            $suffix = $totalFiles > 1 ? " ({$index})" : '';
            $extension = $archivo->getClientOriginalExtension();
            $targetBase = trim($prefix . ' ' . $baseName) . $suffix;
            $targetName = $extension ? $targetBase . '.' . $extension : $targetBase;
            try {
                $drive->uploadEvidence($project, $requirement, $archivo, $targetName, auth()->id());
            } catch (\Throwable $e) {
                return redirect()
                    ->route('projects.manage', $project)
                    ->withErrors(['archivo' => $e->getMessage() ?: 'Error al subir a Drive.']);
            }
            $index++;
        }

        return redirect()->route('projects.manage', $project)->with('status', 'Evidencias cargadas en Drive.');
    }

    private function buildRenumerationMap($requirements): array
    {
        $map = [];
        $grouped = $requirements->groupBy(function ($req) {
            return $req->carpeta ?? 'Sin carpeta';
        });

        foreach ($grouped as $carpeta => $items) {
            $sorted = $items->sortBy(function ($req) {
                return $this->numeracionSortKey($req->codigo_interno ?? $req->numeracion);
            })->values();

            $major = null;
            $width = 2;
            if ($sorted->isNotEmpty()) {
                $first = (string) ($sorted->first()->codigo_interno ?? $sorted->first()->numeracion ?? '');
                if (str_contains($first, '.')) {
                    [$major, $minor] = explode('.', $first, 2);
                    $width = max(2, strlen($minor));
                } elseif ($first !== '') {
                    $width = max(2, strlen($first));
                }
            }

            $counter = 1;
            foreach ($sorted as $req) {
                $formatted = $major !== null
                    ? sprintf('%s.%0' . $width . 'd', $major, $counter)
                    : sprintf('%0' . $width . 'd', $counter);
                $map[$req->id] = $formatted;
                $counter++;
            }
        }

        return $map;
    }

    private function numeracionSortKey(?string $value): string
    {
        if (!$value) {
            return '999999';
        }

        $parts = explode('.', $value);
        $parts = array_map(function ($part) {
            return str_pad($part, 4, '0', STR_PAD_LEFT);
        }, $parts);

        return implode('.', $parts);
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

    private function buildManageSections($requirementsByFolder): array
    {
        $requirementsByFolder = collect($requirementsByFolder->all());
        $sections = [];
        foreach ($requirementsByFolder as $carpeta => $items) {
            $order = 999;
            if (preg_match('/^(\d+)/', (string) $carpeta, $m)) {
                $order = (int) $m[1];
            }
            $sections[] = [
                'type' => 'folder',
                'name' => $carpeta,
                'order' => $order,
                'items' => $items,
                'group_code' => str_pad((string) $order, 2, '0', STR_PAD_LEFT),
            ];
        }

        usort($sections, function ($a, $b) {
            if ($a['order'] === $b['order']) {
                return strcmp((string) $a['name'], (string) $b['name']);
            }
            return $a['order'] <=> $b['order'];
        });

        return $sections;
    }

    private function buildTopGroupProgress(array $folderProgress): array
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

        foreach ($folderProgress as $folder => $progress) {
            $groupCode = $this->detectTopGroupCode((string) $folder);
            if (!$groupCode || !isset($summary[$groupCode])) {
                continue;
            }
            $summary[$groupCode]['total'] += (int) ($progress['total'] ?? 0);
            $summary[$groupCode]['done'] += (int) ($progress['done'] ?? 0);
        }

        foreach ($summary as $code => $item) {
            $percent = $item['total'] > 0
                ? (int) round(($item['done'] / $item['total']) * 100)
                : 0;
            $summary[$code]['percent'] = $percent;
        }

        return $summary;
    }

    private function detectTopGroupCode(string $folder): ?string
    {
        if (preg_match('/^\s*0?([1-5])(?:\D|$)/', $folder, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        $normalized = strtolower(\Illuminate\Support\Str::ascii($folder));
        if (str_contains($normalized, 'formulacion')) {
            return '01';
        }
        if (str_contains($normalized, 'presupuesto')) {
            return '02';
        }
        if (str_contains($normalized, 'certificacion')) {
            return '03';
        }
        if (str_contains($normalized, 'licencias') || str_contains($normalized, 'permisos')) {
            return '04';
        }
        if (str_contains($normalized, 'estudios') || str_contains($normalized, 'disenos')) {
            return '05';
        }

        return null;
    }
}
