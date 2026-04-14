<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use App\Models\Sector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;

class RequirementController extends Controller
{
    public function importForm()
    {
        $this->authorizeAdmin();

        $count = Requirement::count();
        $documents = Requirement::query()
            ->select(['id', 'nombre_documento', 'carpeta'])
            ->orderBy('carpeta')
            ->orderBy('nombre_documento')
            ->get();

        return view('requirements.import', compact('count', 'documents'));
    }

    public function export()
    {
        $this->authorizeAdmin();

        $requirements = Requirement::query()
            ->orderBy('carpeta')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->with('parent')
            ->get([
                'source_id',
                'codigo_norma',
                'codigo_interno',
                'parent_id',
                'texto',
                'sector',
                'tipo',
                'requiere_check',
                'orden',
                'literal',
                'nombre_documento',
                'carpeta',
                'origen',
            ]);

        $fileName = 'requisitos_actuales_' . now()->format('Ymd_His') . '.xlsx';
        $tmpDir = storage_path('app/tmp');
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }
        $tmpPath = $tmpDir . '/' . $fileName;

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            'id',
            'codigo_norma',
            'codigo_interno',
            'parent_id',
            'texto',
            'sector',
            'tipo',
            'requiere_check',
            'orden',
            'Literal',
            'Nombre de documento',
            'carpeta',
            'origen',
        ], null, 'A1');

        $row = 2;
        foreach ($requirements as $req) {
            $sheet->fromArray([
                $req->source_id,
                $req->codigo_norma,
                $req->codigo_interno,
                $req->parent ? $req->parent->source_id : $req->parent_id,
                $req->texto,
                $req->sector,
                $req->tipo,
                $req->requiere_check,
                $req->orden,
                $req->literal,
                $req->nombre_documento,
                $req->carpeta,
                $req->origen,
            ], null, 'A' . $row);
            $row++;
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tmpPath);

        return response()->download($tmpPath, $fileName)->deleteFileAfterSend(true);
    }

    public function import(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,xlsm,csv'],
            'replace_all' => ['nullable', 'in:1'],
            'import_mode' => ['required', 'in:strict_update,update_or_create'],
        ], [
            'archivo.required' => 'Debes seleccionar un archivo.',
            'import_mode.required' => 'Debes seleccionar un modo de importación.',
        ]);

        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        $file = $data['archivo'];
        $path = $file->getRealPath();

        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'csv') {
            $rows = $this->readCsv($path);
        } else {
            $rows = $this->readSpreadsheet($path);
        }

        if (empty($rows)) {
            return back()->withErrors(['archivo' => 'No se encontraron filas válidas en el archivo.']);
        }

        $strictMode = ($data['import_mode'] ?? 'update_or_create') === 'strict_update';
        if ($strictMode && !empty($data['replace_all'])) {
            return back()->withErrors([
                'import_mode' => 'El modo estricto no es compatible con "Borrar y cargar desde cero".',
            ])->withInput();
        }

        DB::beginTransaction();
        try {
            if (!empty($data['replace_all'])) {
                DB::table('requirement_evidences')->delete();
                DB::table('project_requirement')->delete();
                Requirement::query()->delete();
            }

            $sourceToId = Requirement::query()
                ->whereNotNull('source_id')
                ->pluck('id', 'source_id')
                ->mapWithKeys(function ($id, $sourceId) {
                    return [(int) $sourceId => $id];
                })
                ->all();

            $existingBySource = Requirement::query()
                ->select(['id', 'source_id'])
                ->whereNotNull('source_id')
                ->get()
                ->keyBy('source_id');

            $existing = Requirement::query()
                ->select(['id', 'source_id', 'nombre_documento', 'carpeta', 'codigo_interno'])
                ->get()
                ->keyBy(function ($req) {
                    return $this->buildKey($req->source_id, $req->carpeta, $req->nombre_documento, $req->codigo_interno);
                });

            $pendingParents = [];
            $strictErrors = [];
            $updatedCount = 0;
            $createdCount = 0;

            foreach ($rows as $index => $row) {
                $parentSource = null;
                if (!empty($row['parent_id']) && is_numeric($row['parent_id'])) {
                    $parentSource = (int) $row['parent_id'];
                }
                $row['parent_id'] = null;

                $sourceId = $row['source_id'] ?? null;
                $model = null;

                if ($strictMode) {
                    if (empty($sourceId)) {
                        $strictErrors[] = 'Fila ' . ($index + 2) . ': falta ID (source_id).';
                        continue;
                    }
                    if (!$existingBySource->has((int) $sourceId)) {
                        $strictErrors[] = 'Fila ' . ($index + 2) . ': ID ' . (int) $sourceId . ' no existe en la base.';
                        continue;
                    }
                    $model = $existingBySource->get((int) $sourceId);
                } else {
                    if (!empty($sourceId) && $existingBySource->has((int) $sourceId)) {
                        $model = $existingBySource->get((int) $sourceId);
                    } else {
                        $key = $this->buildKey($sourceId, $row['carpeta'] ?? null, $row['nombre_documento'] ?? null, $row['codigo_interno'] ?? null);
                        if ($key !== '' && $existing->has($key)) {
                            $model = $existing->get($key);
                        }
                    }
                }

                if ($model) {
                    $model->update($row);
                    $updatedCount++;
                    if (!empty($sourceId)) {
                        $sourceToId[(int) $sourceId] = $model->id;
                        $existingBySource->put((int) $sourceId, $model);
                    }
                    if ($parentSource !== null) {
                        $pendingParents[] = ['id' => $model->id, 'parent_source' => $parentSource];
                    }
                } else {
                    $created = Requirement::create($row);
                    $createdCount++;
                    if (!empty($sourceId)) {
                        $sourceToId[(int) $sourceId] = $created->id;
                        $existingBySource->put((int) $sourceId, $created);
                    }
                    if ($parentSource !== null) {
                        $pendingParents[] = ['id' => $created->id, 'parent_source' => $parentSource];
                    }
                    $newKey = $this->buildKey($created->source_id, $created->carpeta, $created->nombre_documento, $created->codigo_interno);
                    if ($newKey !== '') {
                        $existing->put($newKey, $created);
                    }
                }
            }

            if (!empty($strictErrors)) {
                throw ValidationException::withMessages([
                    'archivo' => implode(' ', array_slice($strictErrors, 0, 8)),
                ]);
            }

            if ($strictMode && $updatedCount === 0 && $createdCount === 0) {
                throw ValidationException::withMessages([
                    'archivo' => 'No se actualizó ningún requisito en modo estricto.',
                ]);
            }

            if (!empty($pendingParents)) {
                foreach ($pendingParents as $item) {
                    $mappedParent = $sourceToId[$item['parent_source']] ?? null;
                    Requirement::where('id', $item['id'])->update(['parent_id' => $mappedParent]);
                }
            }

            $this->syncSectorsFromRequirements();
            DB::commit();

            $modeLabel = $strictMode ? 'Modo estricto' : 'Modo actualizar + crear';
            $status = $modeLabel . ': ' . $updatedCount . ' actualizados, ' . $createdCount . ' creados.';

            return redirect()->route('requirements.import')->with('status', $status);
        } catch (ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function readSpreadsheet(string $path): array
    {
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($path);
        $rows = [];

        foreach ($spreadsheet->getSheetNames() as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if (!$sheet) {
                continue;
            }

            $sheetRows = $sheet->toArray(null, false, true, true);
            $rows = array_merge($rows, $this->mapRequirementRows($sheetRows));
        }

        return $rows;
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($handle, 0, ',')) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }

        return $this->mapRequirementRows($rows, false);
    }

    private function mapRequirementRows(array $rows, bool $isAssoc = true): array
    {
        $headerRowIndex = null;
        $headerMap = [];
        $requiredHeaders = ['NUMERACION', 'REQUISITO', 'NOMBRE DE DOCUMENTO', 'CARPETA'];
        $unifiedHeaders = [
            'ID',
            'CODIGO_NORMA',
            'CODIGO_INTERNO',
            'PARENT_ID',
            'TEXTO',
            'SECTOR',
            'TIPO',
            'REQUIERE_CHECK',
            'ORDEN',
            'LITERAL',
            'NOMBRE DE DOCUMENTO',
            'CARPETA',
            'ORIGEN',
        ];

        foreach ($rows as $index => $row) {
            $values = $isAssoc ? array_values($row) : $row;
            foreach ($values as $cell) {
                if (!is_string($cell)) {
                    continue;
                }
                $label = mb_strtoupper(trim($cell));
                if (in_array($label, $requiredHeaders, true) || in_array($label, $unifiedHeaders, true)) {
                    $headerRowIndex = $index;
                    $headerMap = $this->buildHeaderMap($row, $isAssoc);
                    break 2;
                }
            }
        }

        if ($headerRowIndex === null || count($headerMap) < 4) {
            return [];
        }

        $result = [];
        $now = now();

        foreach ($rows as $index => $row) {
            if ($index <= $headerRowIndex) {
                continue;
            }

            $numeracion = $this->getCellValue($row, $headerMap['NUMERACION'] ?? null, $isAssoc);
            $requisito = $this->getCellValue($row, $headerMap['REQUISITO'] ?? null, $isAssoc);
            $nombreDocumento = $this->getCellValue($row, $headerMap['NOMBRE DE DOCUMENTO'] ?? null, $isAssoc);
            $carpeta = $this->getCellValue($row, $headerMap['CARPETA'] ?? null, $isAssoc);

            $sourceId = $this->getCellValue($row, $headerMap['ID'] ?? null, $isAssoc);
            $codigoNorma = $this->getCellValue($row, $headerMap['CODIGO_NORMA'] ?? null, $isAssoc);
            $codigoInterno = $this->getCellValue($row, $headerMap['CODIGO_INTERNO'] ?? null, $isAssoc);
            $parentId = $this->getCellValue($row, $headerMap['PARENT_ID'] ?? null, $isAssoc);
            $texto = $this->getCellValue($row, $headerMap['TEXTO'] ?? null, $isAssoc);
            $sector = $this->getCellValue($row, $headerMap['SECTOR'] ?? null, $isAssoc);
            $tipo = $this->getCellValue($row, $headerMap['TIPO'] ?? null, $isAssoc);
            $requiereCheck = $this->getCellValue($row, $headerMap['REQUIERE_CHECK'] ?? null, $isAssoc);
            $orden = $this->getCellValue($row, $headerMap['ORDEN'] ?? null, $isAssoc);
            $literal = $this->getCellValue($row, $headerMap['LITERAL'] ?? null, $isAssoc);
            $origen = $this->getCellValue($row, $headerMap['ORIGEN'] ?? null, $isAssoc);

            $numeracion = $this->formatNumeracion($numeracion);
            $requisito = trim((string) $requisito);
            $nombreDocumento = trim((string) $nombreDocumento);
            $carpeta = trim((string) $carpeta);

            $sourceId = trim((string) $sourceId);
            $codigoNorma = trim((string) $codigoNorma);
            $codigoInterno = trim((string) $codigoInterno);
            $texto = trim((string) $texto);
            $sector = trim((string) $sector);
            $tipo = trim((string) $tipo);
            $requiereCheck = trim((string) $requiereCheck);
            $orden = trim((string) $orden);
            $literal = trim((string) $literal);
            $origen = trim((string) $origen);

            if ($codigoInterno !== '') {
                $numeracion = $codigoInterno;
            }
            if ($texto !== '') {
                $requisito = $texto;
            }

            if ($requisito === '' && $nombreDocumento === '') {
                continue;
            }

            $result[] = [
                'source_id' => is_numeric($sourceId) ? (int) $sourceId : null,
                'codigo_norma' => $codigoNorma !== '' ? $codigoNorma : null,
                'codigo_interno' => $codigoInterno !== '' ? $codigoInterno : null,
                'parent_id' => is_numeric($parentId) ? (int) $parentId : null,
                'texto' => $texto !== '' ? $texto : null,
                'sector' => $sector !== '' ? $sector : null,
                'tipo' => $tipo !== '' ? $tipo : null,
                'requiere_check' => $requiereCheck !== '' ? $requiereCheck : null,
                'orden' => $orden !== '' ? $orden : null,
                'literal' => $literal !== '' ? $literal : null,
                'numeracion' => $numeracion,
                'requisito' => $requisito !== '' ? $requisito : $nombreDocumento,
                'nombre_documento' => $nombreDocumento !== '' ? $nombreDocumento : $requisito,
                'carpeta' => $carpeta,
                'origen' => $origen !== '' ? $origen : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $result;
    }

    private function buildHeaderMap($row, bool $isAssoc): array
    {
        $map = [];
        foreach (($isAssoc ? $row : $row) as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            $label = mb_strtoupper(trim($value));
            if (in_array($label, [
                'ID',
                'CODIGO_NORMA',
                'CODIGO_INTERNO',
                'PARENT_ID',
                'TEXTO',
                'SECTOR',
                'TIPO',
                'REQUIERE_CHECK',
                'ORDEN',
                'LITERAL',
                'NOMBRE DE DOCUMENTO',
                'CARPETA',
                'ORIGEN',
                'NUMERACION',
                'REQUISITO',
            ], true)) {
                $map[$label] = $key;
            }
        }

        return $map;
    }

    private function getCellValue($row, $key, bool $isAssoc)
    {
        if ($key === null) {
            return null;
        }
        if ($isAssoc) {
            return $row[$key] ?? null;
        }
        return $row[$key] ?? null;
    }

    private function formatNumeracion($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $float = (float) $value;
            $int = (int) $float;
            if (abs($float - $int) < 0.00001) {
                return (string) $int;
            }
            return number_format($float, 2, '.', '');
        }

        return trim((string) $value);
    }

    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        if (!$user || !$user->is_admin) {
            abort(403);
        }
    }

    private function buildKey($sourceId, ?string $carpeta, ?string $nombreDocumento, ?string $codigoInterno = null): string
    {
        if ($sourceId !== null && is_numeric($sourceId)) {
            return 'id:' . (int) $sourceId;
        }
        $carpeta = trim((string) $carpeta);
        $nombreDocumento = trim((string) $nombreDocumento);
        $codigoInterno = trim((string) $codigoInterno);
        if ($carpeta === '' && $nombreDocumento === '' && $codigoInterno === '') {
            return '';
        }
        $carpeta = mb_strtolower($this->normalizeKeyPart($carpeta));
        $nombreDocumento = mb_strtolower($this->normalizeKeyPart($nombreDocumento));
        $codigoInterno = mb_strtolower($this->normalizeKeyPart($codigoInterno));
        return $carpeta . '|' . $nombreDocumento . '|' . $codigoInterno;
    }

    private function normalizeKeyPart(string $value): string
    {
        $value = \Illuminate\Support\Str::ascii($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function syncSectorsFromRequirements(): void
    {
        $sectors = Requirement::query()
            ->whereNotNull('sector')
            ->where('sector', '!=', '')
            ->distinct()
            ->orderBy('sector')
            ->pluck('sector');

        $normalized = [];
        foreach ($sectors as $name) {
            $name = trim((string) $name);
            if ($name === '') {
                continue;
            }
            $normalized[] = $name;
            Sector::firstOrCreate(['nombre' => $name]);
        }

        $normalized = array_values(array_unique($normalized));
        if (empty($normalized)) {
            return;
        }

        $stale = Sector::query()
            ->whereNotIn('nombre', $normalized)
            ->get();

        if ($stale->isNotEmpty()) {
            DB::table('project_sector')
                ->whereIn('sector_id', $stale->pluck('id'))
                ->delete();
            Sector::whereIn('id', $stale->pluck('id'))->delete();
        }
    }
}
