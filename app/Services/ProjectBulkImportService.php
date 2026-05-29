<?php

namespace App\Services;

use App\Models\ExecutionYear;
use App\Models\Municipio;
use App\Models\PrioridadEntidad;
use App\Models\ProfesionalAmbiental;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ProjectStatus;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProjectBulkImportService
{
    public function importFromSpreadsheet(string $path, int $actorId, bool $dryRun = false): ProjectBulkImportResult
    {
        $result = new ProjectBulkImportResult();

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $sheet = $spreadsheet->getSheetByName('projects_import') ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, false, true, true);
        if (count($rows) < 2) {
            $result->addMessage(1, 'error', 'La hoja projects_import no contiene filas de datos.');
            return $result;
        }

        $headerRowIndex = $this->detectHeaderRow($rows);
        $headers = array_map(fn ($v) => trim((string) $v), $rows[$headerRowIndex] ?? []);
        $headerMap = [];
        $aliasMap = $this->headerAliasMap();
        foreach ($headers as $col => $header) {
            if ($header !== '') {
                $headerMap[$header] = $col;
                $canonical = $aliasMap[$header] ?? null;
                if ($canonical) {
                    $headerMap[$canonical] = $col;
                }
            }
        }

        $requiredSets = [
            'nombre' => ['nombre', 'NOMBRE CLAVE'],
            'id_proyecto' => ['id_proyecto', 'ID PROYECTO'],
            'objeto_proyecto' => ['objeto_proyecto', 'OBJETO DEL PROYECTO'],
            'funding_source' => ['funding_source', 'VIABILIDAD'],
            // allow IDs template OR manual tracking names
            'municipio_ids' => ['municipio_ids', 'municipio_nombres', 'MUNICIPIO'],
            'sector_principal_id' => ['sector_principal_id', 'sector_principal_nombre', 'SECTOR'],
            // allow absence in legacy sheet, defaulting to auto
            'drive_mode' => ['drive_mode'],
        ];
        foreach ($requiredSets as $label => $candidates) {
            $present = collect($candidates)->contains(fn ($key) => isset($headerMap[$key]));
            if (!$present && $label !== 'drive_mode') {
                $result->addMessage(1, 'error', "Falta la columna requerida: {$label}");
            }
        }
        if (!empty($result->messages)) {
            return $result;
        }

        $drive = app(GoogleDriveService::class);
        $statusService = app(ProjectStatusService::class);
        $defaultStageId = (int) (ProjectStage::query()->where('nombre', 'Preinversión')->value('id') ?? 0);

        for ($i = $headerRowIndex + 1; $i <= count($rows); $i++) {
            $line = $i;
            $excelRow = $rows[$i] ?? [];
            if ($this->isEmptyRow($excelRow)) {
                continue;
            }
            $row = $this->rowToAssoc($excelRow, $headerMap);

            $nombre = trim((string) ($row['nombre'] ?? ''));
            $nombreClave = trim((string) ($row['nombre_clave'] ?? ''));
            $idProyecto = trim((string) ($row['id_proyecto'] ?? ''));
            $objeto = trim((string) ($row['objeto_proyecto'] ?? ''));
            $fundingSource = trim((string) ($row['funding_source'] ?? ''));

            if ($nombre === '' || $idProyecto === '' || $objeto === '' || $fundingSource === '') {
                // Legacy tracking sheets often include detail/activity rows that are not project rows.
                if ($this->looksLikeDetailRow($row)) {
                    continue;
                }
                $result->skipped++;
                $result->addMessage($line, 'error', 'Faltan campos obligatorios.');
                continue;
            }
            if (!in_array($fundingSource, ['sgr', 'propios'], true)) {
                $result->skipped++;
                $result->addMessage($line, 'error', "funding_source inválido: {$fundingSource}.");
                continue;
            }
            if (Project::query()->where('id_proyecto', $idProyecto)->exists()) {
                $result->skipped++;
                $result->addMessage($line, 'error', "id_proyecto {$idProyecto} ya existe.");
                continue;
            }

            $municipioRaw = (string) ($row['municipio_ids'] ?? '');
            if (trim($municipioRaw) === '') {
                $municipioRaw = (string) ($row['municipio_nombres'] ?? '');
            }
            $municipioIds = $this->parseCsvIds((string) ($row['municipio_ids'] ?? ''));
            if (empty($municipioIds)) {
                $municipioIds = $this->municipioIdsByNames((string) ($row['municipio_nombres'] ?? ''));
            }
            if (empty($municipioIds) || Municipio::query()->whereIn('id', $municipioIds)->count() !== count($municipioIds)) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'Municipio no homologado: "' . trim($municipioRaw) . '".');
                continue;
            }

            $sectorPrincipal = (int) ($row['sector_principal_id'] ?? 0);
            if ($sectorPrincipal <= 0 && filled($row['sector_principal_nombre'] ?? null)) {
                $sectorPrincipal = $this->resolveSectorByName((string) $row['sector_principal_nombre']);
            }
            if ($sectorPrincipal <= 0 || !Sector::query()->where('id', $sectorPrincipal)->exists()) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'sector_principal_id inválido.');
                continue;
            }

            $sectorSecundarioIds = collect($this->parseCsvIds((string) ($row['sectores_secundarios'] ?? '')))
                ->reject(fn (int $id): bool => $id === $sectorPrincipal)
                ->values()
                ->all();
            if (!empty($sectorSecundarioIds) && Sector::query()->whereIn('id', $sectorSecundarioIds)->count() !== count($sectorSecundarioIds)) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'sectores_secundarios contiene IDs inválidos.');
                continue;
            }

            $formuladorId = $this->nullableInt($row['formulador_id'] ?? null);
            $estructuradorId = $this->nullableInt($row['estructurador_id'] ?? null);
            if (!$formuladorId && filled($row['formulador_nombre'] ?? null)) {
                $formuladorId = $this->resolveUserByName((string) $row['formulador_nombre']);
            }
            if (!$estructuradorId && filled($row['estructurador_nombre'] ?? null)) {
                $estructuradorId = $this->resolveUserByName((string) $row['estructurador_nombre']);
            }
            $prioridadEstructurador = $this->nullableInt($row['prioridad_estructurador'] ?? null);
            if ($formuladorId && !User::query()->where('id', $formuladorId)->exists()) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'formulador_id inválido.');
                continue;
            }
            if ($estructuradorId && !User::query()->where('id', $estructuradorId)->exists()) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'estructurador_id inválido.');
                continue;
            }
            if ($estructuradorId && $prioridadEstructurador && Project::query()
                ->where('estructurador_id', $estructuradorId)
                ->where('prioridad_estructurador', $prioridadEstructurador)
                ->exists()) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'Ese estructurador ya tiene esa prioridad.');
                continue;
            }

            $attachmentsMinPercent = max(1, min(100, (int) ($row['attachments_min_percent'] ?? 80)));
            $driveMode = trim((string) ($row['drive_mode'] ?? 'auto'));
            if ($driveMode === '') {
                $driveMode = 'auto';
            }
            if (!in_array($driveMode, ['auto', 'manual'], true)) {
                $result->skipped++;
                $result->addMessage($line, 'error', "drive_mode inválido: {$driveMode}.");
                continue;
            }

            $rutaDrive = trim((string) ($row['ruta_drive'] ?? ''));
            $driveFolderId = null;
            if ($driveMode === 'manual') {
                if ($rutaDrive === '') {
                    $result->skipped++;
                    $result->addMessage($line, 'error', 'ruta_drive es obligatoria cuando drive_mode=manual.');
                    continue;
                }
                $driveFolderId = \App\Filament\Resources\ProjectResource::extractDriveFolderId($rutaDrive);
            } elseif (!$dryRun) {
                if ($drive->isAuthorized($actorId)) {
                    try {
                        $folderName = trim($idProyecto . ' - ' . $nombre, ' -');
                        $createdFolder = $drive->createProjectBaseStructure($folderName, $actorId);
                        $driveFolderId = (string) ($createdFolder['id'] ?? '');
                        $rutaDrive = (string) ($createdFolder['url'] ?? '');
                    } catch (\Throwable $e) {
                        $result->warnings++;
                        $result->addMessage($line, 'warning', 'No se pudo crear estructura en Drive: ' . $e->getMessage());
                    }
                } else {
                    $result->warnings++;
                    $result->addMessage($line, 'warning', 'Drive no autorizado: proyecto creado sin carpeta.');
                }
            }

            $stageId = $this->nullableInt($row['project_stage_id'] ?? null) ?: ($defaultStageId ?: null);
            if (!$stageId && filled($row['project_stage_nombre'] ?? null)) {
                $stageId = (int) (ProjectStage::query()->whereRaw('LOWER(nombre)=?', [mb_strtolower(trim((string) $row['project_stage_nombre']))])->value('id') ?? 0);
            }
            if ($stageId && !ProjectStage::query()->where('id', $stageId)->exists()) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'project_stage_id inválido.');
                continue;
            }

            $prioridadEntidadId = $this->nullableInt($row['prioridad_entidad_id'] ?? null);
            if ($prioridadEntidadId && !PrioridadEntidad::query()->where('id', $prioridadEntidadId)->exists()) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'prioridad_entidad_id inválido.');
                continue;
            }

            $projectStatusId = $this->nullableInt($row['project_status_id'] ?? null);
            if (!$projectStatusId && filled($row['project_status_nombre'] ?? null)) {
                $projectStatusId = (int) (ProjectStatus::query()->whereRaw('LOWER(nombre)=?', [mb_strtolower(trim((string) $row['project_status_nombre']))])->value('id') ?? 0);
            }
            if ($projectStatusId && !ProjectStatus::query()->where('id', $projectStatusId)->exists()) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'project_status_id inválido.');
                continue;
            }

            $profesionalAmbientalId = $this->nullableInt($row['profesional_ambiental_id'] ?? null);
            if (!$profesionalAmbientalId && filled($row['profesional_ambiental_nombre'] ?? null)) {
                $profesionalAmbientalId = (int) (ProfesionalAmbiental::query()->whereRaw('LOWER(nombre)=?', [mb_strtolower(trim((string) $row['profesional_ambiental_nombre']))])->value('id') ?? 0);
            }
            if ($profesionalAmbientalId && !ProfesionalAmbiental::query()->where('id', $profesionalAmbientalId)->exists()) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'profesional_ambiental_id inválido.');
                continue;
            }

            $executionYearIds = $this->parseCsvIds((string) ($row['execution_year_ids'] ?? ''));
            if (!empty($executionYearIds) && ExecutionYear::query()->whereIn('id', $executionYearIds)->count() !== count($executionYearIds)) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'execution_year_ids contiene IDs inválidos.');
                continue;
            }

            try {
                if ($dryRun) {
                    $result->created++;
                    $result->addMessage($line, 'ok', 'Fila válida para crear proyecto.');
                    continue;
                }

                $project = Project::query()->create([
                    'nombre' => $nombre,
                    'nombre_clave' => $nombreClave !== '' ? $nombreClave : null,
                    'id_proyecto' => $idProyecto,
                    'objeto_proyecto' => $objeto,
                    'funding_source' => $fundingSource,
                    'municipio' => Municipio::query()->whereIn('id', $municipioIds)->orderBy('nombre')->pluck('nombre')->implode(', '),
                    'ruta_drive' => $rutaDrive !== '' ? $rutaDrive : null,
                    'drive_folder_id' => $driveFolderId ?: null,
                    'formulador_id' => $formuladorId,
                    'estructurador_id' => $estructuradorId,
                    'prioridad_estructurador' => $prioridadEstructurador,
                    'prioridad_entidad_id' => $prioridadEntidadId,
                    'project_status_id' => $projectStatusId ?: null,
                    'profesional_ambiental_id' => $profesionalAmbientalId ?: null,
                    'attachments_min_percent' => $attachmentsMinPercent,
                    'project_stage_id' => $stageId,
                    'valor' => $this->nullableDecimal($row['valor'] ?? null),
                    'bipin' => $this->nullableString($row['bipin'] ?? null),
                    'secretaria' => $this->nullableString($row['secretaria'] ?? null),
                    'duracion_meses' => $this->nullableInt($row['duracion_meses'] ?? null),
                    'poblacion_objetivo' => $this->nullableInt($row['poblacion_objetivo'] ?? null),
                ]);

                $syncData = [$sectorPrincipal => ['is_primary' => true]];
                foreach ($sectorSecundarioIds as $sid) {
                    $syncData[$sid] = ['is_primary' => false];
                }
                $project->sectores()->sync($syncData);
                $project->municipios()->sync($municipioIds);
                if (!empty($executionYearIds)) {
                    $project->executionYears()->sync($executionYearIds);
                }
                if (!$projectStatusId) {
                    $statusService->setByName($project, 'Iniciativa');
                }
                $result->created++;
            } catch (\Throwable $e) {
                $result->skipped++;
                $result->addMessage($line, 'error', 'Error al crear: ' . $e->getMessage());
            }
        }

        Log::info('Project bulk import executed', [
            'actor_id' => $actorId,
            'dry_run' => $dryRun,
            'created' => $result->created,
            'skipped' => $result->skipped,
            'warnings' => $result->warnings,
            'messages' => $result->messages,
        ]);

        return $result;
    }

    private function rowToAssoc(array $row, array $headerMap): array
    {
        $out = [];
        foreach ($headerMap as $header => $col) {
            $out[$header] = $row[$col] ?? null;
        }

        // Alias from manual-tracking spreadsheet (Spanish headers) -> canonical keys
        $aliasMap = $this->headerAliasMap();
        foreach ($aliasMap as $from => $to) {
            if (!array_key_exists($to, $out) || blank($out[$to])) {
                if (array_key_exists($from, $out) && filled($out[$from])) {
                    $out[$to] = $out[$from];
                }
            }
        }

        // Legacy values from manual file
        if (($out['funding_source'] ?? null) === 'Propios') {
            $out['funding_source'] = 'propios';
        }
        if (($out['funding_source'] ?? null) === 'SGR') {
            $out['funding_source'] = 'sgr';
        }

        return $out;
    }

    /** @return array<string,string> */
    private function headerAliasMap(): array
    {
        return [
            'ID PROYECTO' => 'id_proyecto',
            'NOMBRE CLAVE' => 'nombre',
            'SECTOR' => 'sector_principal_nombre',
            'MUNICIPIO' => 'municipio_nombres',
            'VIABILIDAD' => 'funding_source',
            'VALOR MGA' => 'valor',
            'PRIORIDAD ESTRUCTURADOR' => 'prioridad_estructurador',
            'PRIORIDAD AIM' => 'prioridad_entidad_id',
            'ESTRUCTURADOR' => 'estructurador_nombre',
            'FORMULADOR CIUDADANO' => 'formulador_nombre',
            'APOYO AMBIENTAL' => 'profesional_ambiental_nombre',
            'ETAPA' => 'project_stage_nombre',
            'ESTADO' => 'project_status_nombre',
            'BPIN' => 'bipin',
            'DURACIÓN (MESES)' => 'duracion_meses',
            'OBJETO DEL PROYECTO' => 'objeto_proyecto',
            'POBLACIÓN' => 'poblacion_objetivo',
            'SECRETARIA' => 'secretaria',
            'ID ALTERNATIVA' => 'mga_alternative_id',
        ];
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }
        return true;
    }

    /** @return array<int> */
    private function parseCsvIds(string $value): array
    {
        return collect(explode(',', $value))
            ->map(fn (string $raw): int => (int) trim($raw))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }
        $int = (int) $trimmed;
        return $int > 0 ? $int : null;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text !== '' ? $text : null;
    }

    private function nullableDecimal(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        $normalized = str_replace(['$', ' '], '', $text);
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
        if (!is_numeric($normalized)) {
            return null;
        }
        return (float) $normalized;
    }

    private function resolveUserByName(string $name): int
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return 0;
        }
        return (int) (User::query()->whereRaw('LOWER(name)=?', [$name])->value('id') ?? 0);
    }

    private function resolveSectorByName(string $name): int
    {
        $name = mb_strtolower(trim($name));
        if ($name === '') {
            return 0;
        }
        return (int) (Sector::query()->whereRaw('LOWER(nombre)=?', [$name])->value('id') ?? 0);
    }

    /** @return array<int> */
    private function municipioIdsByNames(string $names): array
    {
        $normalized = str_replace([';', '|', '/', '\\', ' y '], ',', mb_strtolower($names));
        $list = collect(explode(',', $normalized))
            ->map(fn (string $name): string => trim(Str::ascii($name)))
            ->filter()
            ->values()
            ->all();
        if (empty($list)) {
            return [];
        }

        $all = Municipio::query()->get(['id', 'nombre']);
        $found = [];
        foreach ($list as $needle) {
            $match = $all->first(function ($m) use ($needle) {
                $name = trim(Str::ascii(mb_strtolower((string) $m->nombre)));
                return $name === $needle || str_contains($name, $needle) || str_contains($needle, $name);
            });
            if ($match) {
                $found[] = (int) $match->id;
            }
        }

        return collect($found)->unique()->values()->all();
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function detectHeaderRow(array $rows): int
    {
        $bestRow = 1;
        $bestCount = -1;
        $max = min(count($rows), 20);
        for ($r = 1; $r <= $max; $r++) {
            $vals = $rows[$r] ?? [];
            $count = 0;
            foreach ($vals as $v) {
                if (trim((string) $v) !== '') {
                    $count++;
                }
            }
            if ($count > $bestCount) {
                $bestCount = $count;
                $bestRow = $r;
            }
        }
        return $bestRow;
    }

    private function looksLikeDetailRow(array $row): bool
    {
        $actividad = trim((string) ($row['ACTIVIDAD'] ?? $row['actividad'] ?? ''));
        $comentario = trim((string) ($row['COMENTARIO'] ?? $row['comentario'] ?? ''));
        $avance = trim((string) ($row['AVANCE'] ?? $row['avance'] ?? ''));
        $estado = trim((string) ($row['ESTADO'] ?? $row['estado'] ?? ''));

        return $actividad !== '' || $comentario !== '' || $avance !== '' || $estado !== '';
    }
}
