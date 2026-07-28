<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectBankActivityRow;
use App\Models\ProjectBankFinancingRow;
use App\Models\ProjectBankProfile;
use App\Models\ProjectBankSignatory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProjectBankExcelService
{
    public function ensureSeeded(Project $project): void
    {
        $project->loadMissing(['producto', 'formulador', 'sectores', 'municipios']);

        DB::transaction(function () use ($project): void {
            $allowedRoles = collect(config('bank_excel_map.default_signatories', []))
                ->pluck('role')
                ->filter()
                ->values()
                ->all();

            $profile = ProjectBankProfile::query()->firstOrCreate(
                ['project_id' => $project->id],
                $this->defaultProfileData($project)
            );
            $profileWasCreated = $profile->wasRecentlyCreated;

            if ($profile->horizonte_anio_0 === null) {
                $profile->fill($this->defaultProfileData($project))->save();
            }

            foreach (config('bank_excel_map.default_signatories', []) as $row) {
                $role = (string) ($row['role'] ?? 'firmante');
                $seed = $this->seedSignatoryForRole($project, $role);
                $signatory = ProjectBankSignatory::query()->firstOrCreate(
                    ['project_id' => $project->id, 'role' => $role],
                    ['orden' => (int) ($row['orden'] ?? 0)]
                );
                $expectedOrder = (int) ($row['orden'] ?? 0);
                if ((int) $signatory->orden !== $expectedOrder) {
                    $signatory->orden = $expectedOrder;
                }
                if (trim((string) $signatory->nombre) === '' && ! empty($seed['nombre'])) {
                    $signatory->nombre = $seed['nombre'];
                }
                if (trim((string) $signatory->cargo) === '' && ! empty($seed['cargo'])) {
                    $signatory->cargo = $seed['cargo'];
                }
                if (trim((string) $signatory->correo) === '' && ! empty($seed['correo'])) {
                    $signatory->correo = $seed['correo'];
                }
                if (trim((string) $signatory->telefono) === '' && ! empty($seed['telefono'])) {
                    $signatory->telefono = $seed['telefono'];
                }
                if ($signatory->isDirty()) {
                    $signatory->save();
                }
            }

            ProjectBankSignatory::query()
                ->where('project_id', $project->id)
                ->whereNotIn('role', $allowedRoles)
                ->delete();

            if ($profileWasCreated && ! ProjectBankActivityRow::query()->where('project_id', $project->id)->exists()) {
                $productMga = (string) ($project->producto?->nombre_con_codigo ?? '');
                foreach (array_values(config('bank_excel_map.default_activity_names', [])) as $index => $name) {
                    ProjectBankActivityRow::query()->create([
                        'project_id' => $project->id,
                        'orden' => $index + 1,
                        'actividad' => (string) $name,
                        'producto_mga' => $productMga,
                        'valor_actividad' => null,
                    ]);
                }
            }

            if ($profileWasCreated && ! ProjectBankFinancingRow::query()->where('project_id', $project->id)->exists()) {
                $activities = ProjectBankActivityRow::query()
                    ->where('project_id', $project->id)
                    ->orderBy('orden')
                    ->get();
                foreach ($activities as $activity) {
                    ProjectBankFinancingRow::query()->create([
                        'project_id' => $project->id,
                        'orden' => $activity->orden,
                        'actividad' => $activity->actividad,
                        'producto_mga' => $activity->producto_mga,
                        'valor_actividad' => $activity->valor_actividad,
                        'municipio_relacion' => $project->municipios->pluck('nombre')->implode(', '),
                    ]);
                }
            }

            $this->syncFinancingWithActivities($project);
        });
    }

    public function profileFor(Project $project): ProjectBankProfile
    {
        $this->ensureSeeded($project);

        return ProjectBankProfile::query()->where('project_id', $project->id)->firstOrFail();
    }

    public function signatoriesFor(Project $project)
    {
        $this->ensureSeeded($project);
        $allowedRoles = collect(config('bank_excel_map.default_signatories', []))
            ->pluck('role')
            ->filter()
            ->values()
            ->all();

        return ProjectBankSignatory::query()
            ->where('project_id', $project->id)
            ->whereIn('role', $allowedRoles)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    public function financingRowsFor(Project $project)
    {
        $this->ensureSeeded($project);
        $this->syncFinancingWithActivities($project);

        return ProjectBankFinancingRow::query()
            ->where('project_id', $project->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    public function activityRowsFor(Project $project)
    {
        $this->ensureSeeded($project);

        return ProjectBankActivityRow::query()
            ->where('project_id', $project->id)
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    public function saveProfile(Project $project, array $data): void
    {
        $project->loadMissing(['sectores', 'producto', 'municipios']);
        $profile = $this->profileFor($project);
        $profile->fill($data);
        $profile->sector_texto_plantilla = $this->resolvePrimarySectorLabel($project);
        if (empty($profile->vigencia)) {
            $profile->vigencia = $profile->horizonte_anio_0 ?: now()->year;
        }
        $profile->save();
        $this->syncFinancingWithActivities($project);
    }

    public function replaceSignatories(Project $project, array $rows): void
    {
        DB::transaction(function () use ($project, $rows): void {
            ProjectBankSignatory::query()->where('project_id', $project->id)->delete();
            foreach ($rows as $index => $row) {
                if ($this->isRowEmpty($row, ['role', 'nombre', 'cargo', 'correo', 'telefono'])) {
                    continue;
                }
                ProjectBankSignatory::query()->create([
                    'project_id' => $project->id,
                    'orden' => (int) ($row['orden'] ?? ($index + 1)),
                    'role' => trim((string) ($row['role'] ?? 'firmante')),
                    'nombre' => $this->nullableString($row['nombre'] ?? null),
                    'cargo' => $this->nullableString($row['cargo'] ?? null),
                    'correo' => $this->nullableString($row['correo'] ?? null),
                    'telefono' => $this->nullableString($row['telefono'] ?? null),
                ]);
            }
        });
    }

    public function replaceFinancingRows(Project $project, array $rows): void
    {
        // Mantenido por compatibilidad; ahora la fuente de verdad es el perfil + cadena de valor.
        $this->syncFinancingWithActivities($project);
    }

    public function replaceActivityRows(Project $project, array $rows): void
    {
        DB::transaction(function () use ($project, $rows): void {
            ProjectBankActivityRow::query()->where('project_id', $project->id)->delete();
            foreach ($rows as $index => $row) {
                if ($this->isRowEmpty($row, ['actividad', 'valor_actividad', 'producto_mga', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'])) {
                    continue;
                }
                ProjectBankActivityRow::query()->create([
                    'project_id' => $project->id,
                    'orden' => (int) ($row['orden'] ?? ($index + 1)),
                    'actividad' => (string) ($row['actividad'] ?? ''),
                    'producto_mga' => $this->nullableString($row['producto_mga'] ?? null),
                    'valor_actividad' => $this->nullableDecimal($row['valor_actividad'] ?? null),
                    'ene' => ! empty($row['ene']),
                    'feb' => ! empty($row['feb']),
                    'mar' => ! empty($row['mar']),
                    'abr' => ! empty($row['abr']),
                    'may' => ! empty($row['may']),
                    'jun' => ! empty($row['jun']),
                    'jul' => ! empty($row['jul']),
                    'ago' => ! empty($row['ago']),
                    'sep' => ! empty($row['sep']),
                    'oct' => ! empty($row['oct']),
                    'nov' => ! empty($row['nov']),
                    'dic' => ! empty($row['dic']),
                ]);
            }
            $this->syncFinancingWithActivities($project);
        });
    }

    public function missingRequiredFields(Project $project): array
    {
        $profile = $this->profileFor($project);
        $signatories = $this->signatoriesFor($project);
        $activities = $this->activityRowsFor($project);

        $missing = [];

        if ($profile->vigencia === null) {
            $missing[] = 'Vigencia';
        }

        if (trim((string) $project->nombre) === '') {
            $missing[] = 'Nombre del proyecto';
        }

        if (trim((string) $project->id_proyecto) === '') {
            $missing[] = 'ID del proyecto';
        }

        foreach (['elaboro', 'aprobo'] as $role) {
            $item = $signatories->firstWhere('role', $role);
            if (! $item || trim((string) $item->nombre) === '') {
                $missing[] = 'Firmante '.$role;
            }
        }

        if ($activities->isEmpty()) {
            $missing[] = 'Al menos una actividad en cronograma';
        }

        return array_values(array_unique($missing));
    }

    public function generateExcel(Project $project, string $templateType): string
    {
        $this->ensureSeeded($project);
        $missing = $this->missingRequiredFields($project);
        if (! empty($missing)) {
            throw new \RuntimeException('Faltan datos requeridos: '.implode(', ', $missing));
        }

        $templatePath = (string) data_get(config('bank_excel_map.template_paths'), $templateType);
        if ($templatePath === '' || ! is_file($templatePath)) {
            throw new \RuntimeException('No se encontró la plantilla base para '.$templateType);
        }

        $sheetName = (string) data_get(config('bank_excel_map.sheet_names'), $templateType);
        $spreadsheet = IOFactory::load($templatePath);
        $sheet = $sheetName !== '' ? $spreadsheet->getSheetByName($sheetName) : $spreadsheet->getActiveSheet();
        if (! $sheet) {
            $sheet = $spreadsheet->getActiveSheet();
        }

        $project->loadMissing(['producto', 'sectores', 'municipios', 'formulador']);
        $profile = $this->profileFor($project);
        $signatories = $this->signatoriesFor($project)->keyBy('role');
        $financingRows = $this->financingRowsFor($project);
        $activityRows = $this->activityRowsFor($project);

        $projectName = (string) ($profile->proyecto_titulo_override ?: $project->nombre);
        $vigencia = (int) ($profile->vigencia ?: ($profile->horizonte_anio_0 ?: now()->year));
        $productMga = (string) ($project->producto?->nombre_con_codigo ?? '');
        $sectorText = (string) ($profile->sector_texto_plantilla ?: optional($project->sectores->firstWhere('pivot.is_primary', true))->nombre_con_codigo);
        $municipios = $project->municipios->pluck('nombre')->implode(', ');

        $cells = (array) data_get(config('bank_excel_map.cells'), $templateType, []);
        $this->setIfCell($sheet, $cells, 'codigo_dependencia', $profile->codigo_dependencia);
        $this->setIfCell($sheet, $cells, 'dependencia', $profile->dependencia ?: 'AGENCIA PARA LA INFRAESTRUCTURA DEL META');
        $this->setIfCell($sheet, $cells, 'proyecto', $projectName);
        $this->setIfCell($sheet, $cells, 'vigencia', $vigencia);

        if ($templateType === 'bank_cronograma') {
            $this->setIfCell($sheet, $cells, 'fecha_firma', 'La presente se firma el día '.Carbon::now()->format('d/m/Y'));
        }

        $elaboro = $signatories->get('elaboro');
        $aprobo = $signatories->get('aprobo');
        $base = $signatories->get('formulador_oficial');

        $tableMap = (array) data_get(config('bank_excel_map.table_rows'), $templateType, []);
        $startRow = (int) ($tableMap['start_row'] ?? 0);
        $columns = (array) ($tableMap['columns'] ?? []);
        $this->clearTableRows($sheet, $columns, $startRow, 220);

        if ($templateType === 'bank_plan_inversion') {
            foreach ($financingRows as $idx => $row) {
                $r = $startRow + $idx;
                $this->setRowCell($sheet, $columns, 'producto_mga', $r, $row->producto_mga ?: $productMga);
                $this->setRowCell($sheet, $columns, 'beneficiarios', $r, $row->beneficiarios);
                $this->setRowCell($sheet, $columns, 'actividad', $r, $row->actividad);
                $this->setRowCell($sheet, $columns, 'valor_actividad', $r, $row->valor_actividad);
                $this->setRowCell($sheet, $columns, 'codigo_fuente', $r, $row->codigo_fuente);
                $this->setRowCell($sheet, $columns, 'nombre_fuente', $r, $row->nombre_fuente);
                $this->setRowCell($sheet, $columns, 'meta_plan_codigo', $r, $row->meta_plan_codigo ?: $profile->meta_plan_codigo);
                $this->setRowCell($sheet, $columns, 'meta_plan_nombre', $r, $row->meta_plan_nombre ?: $profile->meta_plan_nombre);
                $this->setRowCell($sheet, $columns, 'municipio_relacion', $r, $row->municipio_relacion ?: $municipios);
            }
            $footerRow = $this->minRowFromCells($cells, [
                'firma_elaboro_nombre',
                'firma_aprobo_nombre',
            ]);
            $this->hideUnusedRows($sheet, $startRow, $financingRows->count(), $footerRow > 0 ? $footerRow - 1 : ($startRow + 220));
        }

        if ($templateType === 'bank_plan_desarrollo') {
            foreach ($financingRows as $idx => $row) {
                $r = $startRow + $idx;
                $this->setRowCell($sheet, $columns, 'actividad', $r, $row->actividad);
                $this->setRowCell($sheet, $columns, 'valor_actividad', $r, $row->valor_actividad);
                $this->setRowCell($sheet, $columns, 'pilar', $r, $this->extractPilarNumber($profile->pilar));
                $this->setRowCell($sheet, $columns, 'eje', $r, $profile->eje);
                $this->setRowCell($sheet, $columns, 'linea', $r, $profile->linea);
                $this->setRowCell($sheet, $columns, 'programa', $r, $profile->programa);
                $this->setRowCell($sheet, $columns, 'subprograma', $r, $profile->subprograma);
                $this->setRowCell($sheet, $columns, 'sector_texto_plantilla', $r, $profile->sector_texto_plantilla ?: $sectorText);
                $this->setRowCell($sheet, $columns, 'meta_plan_codigo', $r, $row->meta_plan_codigo ?: $profile->meta_plan_codigo);
                $this->setRowCell($sheet, $columns, 'meta_plan_nombre', $r, $row->meta_plan_nombre ?: $profile->meta_plan_nombre);
            }
            $footerRow = $this->minRowFromCells($cells, [
                'firma_elaboro_nombre',
                'firma_aprobo_nombre',
            ]);
            $this->hideUnusedRows($sheet, $startRow, $financingRows->count(), $footerRow > 0 ? $footerRow - 1 : ($startRow + 220));
        }

        if ($templateType === 'bank_cronograma') {
            foreach ($activityRows as $idx => $row) {
                $r = $startRow + $idx;
                $this->setRowCell($sheet, $columns, 'actividad', $r, $row->actividad);
                foreach (['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'] as $m) {
                    $this->setRowCell($sheet, $columns, $m, $r, $row->{$m} ? 'X' : null);
                }
            }
            $footerRow = $this->rowFromCell((string) ($cells['fecha_firma'] ?? ''));
            $this->hideUnusedRows($sheet, $startRow, $activityRows->count(), $footerRow > 0 ? $footerRow - 1 : ($startRow + 220));
        }

        $this->setSignatureCell($sheet, $cells, 'firma_base_nombre', $base?->nombre);
        $this->setSignatureCell($sheet, $cells, 'firma_base_cargo', $base?->cargo);
        $this->setSignatureCell($sheet, $cells, 'firma_base_correo', $base?->correo);
        $this->setSignatureCell($sheet, $cells, 'firma_base_telefono', $base?->telefono);
        $this->setSignatureCell($sheet, $cells, 'firma_elaboro_nombre', $elaboro?->nombre);
        $this->setSignatureCell($sheet, $cells, 'firma_elaboro_cargo', $elaboro?->cargo);
        $this->setSignatureCell($sheet, $cells, 'firma_elaboro_correo', $elaboro?->correo);
        $this->setSignatureCell($sheet, $cells, 'firma_elaboro_telefono', $elaboro?->telefono);
        $this->setSignatureCell($sheet, $cells, 'firma_aprobo_nombre', $aprobo?->nombre);
        $this->setSignatureCell($sheet, $cells, 'firma_aprobo_cargo', $aprobo?->cargo);
        $this->setSignatureCell($sheet, $cells, 'firma_aprobo_correo', $aprobo?->correo);
        $this->setSignatureCell($sheet, $cells, 'firma_aprobo_telefono', $aprobo?->telefono);

        $tmpDir = storage_path('app/tmp/bank_excels');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $safeProject = Str::slug((string) $project->nombre, '_');
        $output = $tmpDir.'/'.$safeProject.'_'.$templateType.'_'.now()->format('Ymd_His').'.xlsx';
        $this->sanitizeDefinedNames($spreadsheet);
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($output);

        return $output;
    }

    private function defaultProfileData(Project $project): array
    {
        $year = $project->fecha_creacion ? (int) Carbon::parse($project->fecha_creacion)->format('Y') : now()->year;

        return [
            'horizonte_anio_0' => $year,
            'horizonte_anio_1' => $year + 1,
            'horizonte_anio_2' => null,
            'horizonte_anio_3' => null,
            'tipo_presentacion' => 'proyecto',
            'tipo_tramite' => 'actualizacion',
            'codigo_dependencia' => '26',
            'dependencia' => 'AGENCIA PARA LA INFRAESTRUCTURA DEL META',
            'vigencia' => $year,
            'proyecto_titulo_override' => $project->nombre,
            'sector_texto_plantilla' => $this->resolvePrimarySectorLabel($project),
        ];
    }

    private function syncFinancingWithActivities(Project $project): void
    {
        $project->loadMissing(['producto', 'municipios', 'sectores']);
        $profile = ProjectBankProfile::query()->where('project_id', $project->id)->first();
        if ($profile && $profile->sector_texto_plantilla !== $this->resolvePrimarySectorLabel($project)) {
            $profile->sector_texto_plantilla = $this->resolvePrimarySectorLabel($project);
            $profile->save();
        }
        $activities = ProjectBankActivityRow::query()
            ->where('project_id', $project->id)
            ->orderBy('orden')
            ->get();

        $existing = ProjectBankFinancingRow::query()
            ->where('project_id', $project->id)
            ->orderBy('orden')
            ->get()
            ->keyBy('orden');

        $activityOrders = [];

        foreach ($activities as $activity) {
            $activityOrders[] = (int) $activity->orden;
            $target = $existing->get((int) $activity->orden);
            if (! $target) {
                $target = new ProjectBankFinancingRow;
                $target->project_id = $project->id;
                $target->orden = (int) $activity->orden;
            }

            $target->actividad = $activity->actividad;
            $target->producto_mga = (string) ($project->producto?->nombre_con_codigo ?: $activity->producto_mga);
            $target->valor_actividad = $activity->valor_actividad;
            $target->codigo_fuente = $profile?->codigo_fuente;
            $target->nombre_fuente = $profile?->nombre_fuente;
            $target->meta_plan_codigo = $profile?->meta_plan_codigo;
            $target->meta_plan_nombre = $profile?->meta_plan_nombre;
            $target->municipio_relacion = $profile?->municipio_relacion ?: $project->municipios->pluck('nombre')->implode(', ');
            $target->beneficiarios = $profile?->beneficiarios;
            $target->save();
        }

        if (! empty($activityOrders)) {
            ProjectBankFinancingRow::query()
                ->where('project_id', $project->id)
                ->whereNotIn('orden', $activityOrders)
                ->delete();
        } else {
            ProjectBankFinancingRow::query()
                ->where('project_id', $project->id)
                ->delete();
        }
    }

    private function seedSignatoryForRole(Project $project, string $role): array
    {
        if ($role === 'elaboro') {
            return [
                'nombre' => $project->formulador?->name,
                'cargo' => 'Formulador de proyectos',
                'correo' => $project->formulador?->email,
                'telefono' => null,
            ];
        }

        if ($role === 'aprobo') {
            return [
                'nombre' => $project->estructurador?->name ?: $project->formulador?->name,
                'cargo' => 'Aprobador',
                'correo' => $project->estructurador?->email ?: $project->formulador?->email,
                'telefono' => null,
            ];
        }

        if ($role === 'formulador_oficial') {
            return [
                'nombre' => $project->formulador?->name,
                'cargo' => 'Formulador oficial',
                'correo' => $project->formulador?->email,
                'telefono' => null,
            ];
        }

        return [];
    }

    private function resolvePrimarySectorLabel(Project $project): ?string
    {
        $primary = $project->sectores->firstWhere('pivot.is_primary', true);
        if (! $primary && $project->relationLoaded('sectores')) {
            $primary = $project->sectores->first();
        }

        return $primary?->nombre_con_codigo ?? $primary?->nombre;
    }

    private function setIfCell($sheet, array $cells, string $key, $value): void
    {
        $cell = $cells[$key] ?? null;
        if (! $cell) {
            return;
        }

        $sheet->setCellValue($cell, $this->sanitizeExcelValue($value));
    }

    private function setSignatureCell($sheet, array $cells, string $key, ?string $value): void
    {
        $cell = $cells[$key] ?? null;
        if (! $cell) {
            return;
        }
        $newValue = trim((string) $value);
        if ($newValue === '') {
            return;
        }

        $existing = (string) $sheet->getCell($cell)->getValue();
        if (preg_match('/^(.*?:)\s*.*$/u', $existing, $m)) {
            $sheet->setCellValue($cell, $this->sanitizeExcelValue($m[1].' '.$newValue));

            return;
        }
        if (preg_match('/^(\s+)/u', $existing, $m)) {
            $sheet->setCellValue($cell, $this->sanitizeExcelValue($m[1].$newValue));

            return;
        }

        $sheet->setCellValue($cell, $this->sanitizeExcelValue($newValue));
    }

    private function setRowCell($sheet, array $columns, string $key, int $row, $value): void
    {
        $col = $columns[$key] ?? null;
        if (! $col) {
            return;
        }
        $sheet->setCellValue($col.$row, $this->sanitizeExcelValue($value));
    }

    private function clearTableRows($sheet, array $columns, int $startRow, int $maxRows): void
    {
        if ($startRow <= 0 || $maxRows <= 0) {
            return;
        }

        $endRow = $startRow + $maxRows - 1;
        foreach (array_unique(array_values($columns)) as $col) {
            for ($r = $startRow; $r <= $endRow; $r++) {
                $sheet->setCellValue($col.$r, null);
            }
        }
    }

    private function sanitizeExcelValue($value)
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace('/[^\P{C}\t\n\r]/u', '', $value);
    }

    private function sanitizeDefinedNames($spreadsheet): void
    {
        foreach ($spreadsheet->getDefinedNames() as $definedName) {
            $value = (string) $definedName->getValue();
            if (str_contains($value, '#REF!') || preg_match('/\\[[^\\]]+\\]/', $value)) {
                $spreadsheet->removeDefinedName(
                    $definedName->getName(),
                    $definedName->getScope()
                );
            }
        }
    }

    private function mergeLabel(string $label, ?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $label.':';
        }

        return $label.': '.$value;
    }

    private function extractPilarNumber(?string $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        if (preg_match('/\b(\d+)\b/', $text, $m)) {
            return $m[1];
        }

        return $text;
    }

    private function rowFromCell(string $cell): int
    {
        if (preg_match('/(\d+)/', $cell, $m)) {
            return (int) $m[1];
        }

        return 0;
    }

    private function minRowFromCells(array $cells, array $keys): int
    {
        $rows = [];
        foreach ($keys as $key) {
            $row = $this->rowFromCell((string) ($cells[$key] ?? ''));
            if ($row > 0) {
                $rows[] = $row;
            }
        }

        return empty($rows) ? 0 : min($rows);
    }

    private function hideUnusedRows($sheet, int $startRow, int $usedRows, int $lastDataRow): void
    {
        if ($startRow <= 0 || $lastDataRow < $startRow) {
            return;
        }

        $lastUsedRow = max($startRow, $startRow + max(0, $usedRows) - 1);
        for ($row = $startRow; $row <= $lastDataRow; $row++) {
            $sheet->getRowDimension($row)->setVisible($row <= $lastUsedRow);
        }
    }

    private function isRowEmpty(array $row, array $keys): bool
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }
            $value = $row[$key];
            if (is_bool($value) && $value) {
                return false;
            }
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function nullableString($value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function nullableDecimal($value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $raw = trim((string) $value);
        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $normalized = str_replace('.', '', $raw);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($raw, ',')) {
            $normalized = str_replace(',', '.', $raw);
        } else {
            $normalized = $raw;
        }
        if (! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (int) $value;
    }
}
