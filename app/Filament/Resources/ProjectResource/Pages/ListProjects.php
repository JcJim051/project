<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Models\ExecutionYear;
use App\Models\Municipio;
use App\Models\ProjectStage;
use App\Models\Sector;
use App\Models\User;
use App\Services\ProjectBulkImportService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('plantillaCargaMasiva')
                ->label('Descargar plantilla Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->visible(fn (): bool => (bool) auth()->user()?->canManageDirectorCatalogs())
                ->action(function () {
                    $spreadsheet = new Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle('projects_import');

                    $headers = [
                        'nombre',
                        'nombre_clave',
                        'id_proyecto',
                        'objeto_proyecto',
                        'funding_source',
                        'municipio_ids',
                        'sector_principal_id',
                        'sectores_secundarios',
                        'formulador_id',
                        'estructurador_id',
                        'prioridad_estructurador',
                        'prioridad_entidad_id',
                        'profesional_ambiental_id',
                        'attachments_min_percent',
                        'drive_mode',
                        'ruta_drive',
                        'project_stage_id',
                        'project_status_id',
                        'valor',
                        'bipin',
                        'secretaria',
                        'duracion_meses',
                        'poblacion_objetivo',
                        'execution_year_ids',
                    ];
                    $requiredMap = [
                        'nombre' => 'Requerido',
                        'id_proyecto' => 'Requerido',
                        'nombre_clave' => 'Opcional',
                        'objeto_proyecto' => 'Requerido',
                        'funding_source' => 'Requerido',
                        'municipio_ids' => 'Requerido',
                        'sector_principal_id' => 'Requerido',
                        'sectores_secundarios' => 'Opcional',
                        'formulador_id' => 'Opcional',
                        'estructurador_id' => 'Opcional',
                        'prioridad_estructurador' => 'Opcional',
                        'prioridad_entidad_id' => 'Opcional',
                        'profesional_ambiental_id' => 'Opcional',
                        'attachments_min_percent' => 'Opcional (default 80)',
                        'drive_mode' => 'Requerido',
                        'ruta_drive' => 'Opcional (obligatorio si drive_mode=manual)',
                        'project_stage_id' => 'Opcional (default Preinversión)',
                        'project_status_id' => 'Opcional (si vacío queda Iniciativa)',
                        'valor' => 'Opcional',
                        'bipin' => 'Opcional',
                        'secretaria' => 'Opcional',
                        'duracion_meses' => 'Opcional',
                        'poblacion_objetivo' => 'Opcional',
                        'execution_year_ids' => 'Opcional',
                    ];
                    foreach ($headers as $idx => $header) {
                        $col = Coordinate::stringFromColumnIndex($idx + 1);
                        $sheet->setCellValue("{$col}1", $header);
                        $sheet->setCellValue("{$col}2", $requiredMap[$header] ?? 'Opcional');
                    }
                    $example = [
                        'Hospital Gaitan',
                        'Hospital Gaitan',
                        '1446681',
                        'Objeto resumido del proyecto',
                        'sgr',
                        '1,5',
                        '2',
                        '3,4',
                        '',
                        '',
                        '',
                        '1',
                        '80',
                        'auto',
                        '',
                        '1',
                        '',
                        '8997160',
                        '',
                        '',
                        '18',
                        '15000',
                        '1,2',
                    ];
                    foreach ($example as $idx => $value) {
                        $col = Coordinate::stringFromColumnIndex($idx + 1);
                        $sheet->setCellValue("{$col}3", $value);
                    }
                    $sheet->setCellValue('A4', 'Tip: en campos de múltiples IDs usa coma, por ejemplo: 1,5,9');

                    $help = $spreadsheet->createSheet();
                    $help->setTitle('guia_campos');
                    $help->setCellValue('A1', 'Campo');
                    $help->setCellValue('B1', 'Obligatoriedad');
                    $help->setCellValue('C1', 'Formato / ejemplo');
                    $row = 2;
                    foreach ($headers as $header) {
                        $help->setCellValue("A{$row}", $header);
                        $help->setCellValue("B{$row}", $requiredMap[$header] ?? 'Opcional');
                        $exampleValue = match ($header) {
                            'funding_source' => 'sgr o propios',
                            'municipio_ids' => '1,5,9',
                            'sectores_secundarios' => '2,4',
                            'drive_mode' => 'auto o manual',
                            'ruta_drive' => 'URL o ID de carpeta',
                            'project_status_id' => 'ID estado',
                            'valor' => '8997160 o 8.997.160,00',
                            'execution_year_ids' => '1,2',
                            default => '',
                        };
                        $help->setCellValue("C{$row}", $exampleValue);
                        $row++;
                    }

                    $catalogs = $spreadsheet->createSheet();
                    $catalogs->setTitle('catalogos_id');
                    $catalogs->setCellValue('A1', 'Catálogos de apoyo (IDs)');
                    $catalogs->setCellValue('A3', 'Municipios');
                    $catalogs->setCellValue('D3', 'Sectores');
                    $catalogs->setCellValue('G3', 'Usuarios (Formulador/Estructurador)');
                    $catalogs->setCellValue('J3', 'Etapas');
                    $catalogs->setCellValue('M3', 'Años ejecución');
                    $catalogs->setCellValue('P3', 'Prioridad entidad');
                    $catalogs->setCellValue('S3', 'Estado proyecto');
                    $catalogs->setCellValue('V3', 'Profesional ambiental');

                    $r = 4;
                    foreach (Municipio::query()->orderBy('nombre')->get(['id', 'nombre']) as $m) {
                        $catalogs->setCellValue("A{$r}", (int) $m->id);
                        $catalogs->setCellValue("B{$r}", (string) $m->nombre);
                        $r++;
                    }
                    $r = 4;
                    foreach (Sector::query()->orderBy('codigo')->get(['id', 'codigo', 'nombre']) as $s) {
                        $catalogs->setCellValue("D{$r}", (int) $s->id);
                        $catalogs->setCellValue("E{$r}", trim((string) $s->codigo . ' ' . (string) $s->nombre));
                        $r++;
                    }
                    $r = 4;
                    foreach (User::query()->orderBy('name')->get(['id', 'name']) as $u) {
                        $catalogs->setCellValue("G{$r}", (int) $u->id);
                        $catalogs->setCellValue("H{$r}", (string) $u->name);
                        $r++;
                    }
                    $r = 4;
                    foreach (ProjectStage::query()->orderBy('nombre')->get(['id', 'nombre']) as $st) {
                        $catalogs->setCellValue("J{$r}", (int) $st->id);
                        $catalogs->setCellValue("K{$r}", (string) $st->nombre);
                        $r++;
                    }
                    $r = 4;
                    foreach (ExecutionYear::query()->orderBy('anio')->get(['id', 'anio']) as $y) {
                        $catalogs->setCellValue("M{$r}", (int) $y->id);
                        $catalogs->setCellValue("N{$r}", (int) $y->anio);
                        $r++;
                    }
                    $r = 4;
                    foreach (\App\Models\PrioridadEntidad::query()->orderBy('numero')->get(['id', 'numero', 'nombre']) as $p) {
                        $catalogs->setCellValue("P{$r}", (int) $p->id);
                        $catalogs->setCellValue("Q{$r}", trim((string) $p->numero . ' ' . (string) $p->nombre));
                        $r++;
                    }
                    $r = 4;
                    foreach (\App\Models\ProjectStatus::query()->orderBy('orden')->get(['id', 'nombre']) as $status) {
                        $catalogs->setCellValue("S{$r}", (int) $status->id);
                        $catalogs->setCellValue("T{$r}", (string) $status->nombre);
                        $r++;
                    }
                    $r = 4;
                    foreach (\App\Models\ProfesionalAmbiental::query()->orderBy('nombre')->get(['id', 'nombre']) as $pa) {
                        $catalogs->setCellValue("V{$r}", (int) $pa->id);
                        $catalogs->setCellValue("W{$r}", (string) $pa->nombre);
                        $r++;
                    }

                    $tmpPath = storage_path('app/tmp/plantilla_carga_masiva_proyectos.xlsx');
                    if (!is_dir(dirname($tmpPath))) {
                        mkdir(dirname($tmpPath), 0775, true);
                    }

                    $writer = new Xlsx($spreadsheet);
                    $writer->save($tmpPath);

                    return response()->download($tmpPath, 'plantilla_carga_masiva_proyectos.xlsx')->deleteFileAfterSend(true);
                }),
            Actions\Action::make('cargaMasivaProyectos')
                ->label('Cargar Excel de proyectos')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn (): bool => (bool) auth()->user()?->canManageDirectorCatalogs())
                ->form([
                    FileUpload::make('archivo')
                        ->label('Archivo Excel')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->disk('local')
                        ->directory('imports')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $relativePath = (string) ($data['archivo'] ?? '');
                    $filePath = Storage::disk('local')->path($relativePath);
                    $result = app(ProjectBulkImportService::class)
                        ->importFromSpreadsheet($filePath, (int) auth()->id());

                    $body = "Creados: {$result->created} | Omitidos: {$result->skipped} | Advertencias: {$result->warnings}";
                    if (!empty($result->messages)) {
                        $top = array_slice($result->messages, 0, 10);
                        $body .= "\n" . collect($top)
                                ->map(fn (array $m): string => "Fila {$m['line']} [{$m['type']}]: {$m['message']}")
                                ->implode("\n");
                        if (count($result->messages) > 10) {
                            $body .= "\n... y " . (count($result->messages) - 10) . " más.";
                        }
                    }

                    $notification = Notification::make()->title('Carga masiva finalizada')->body($body);
                    if ($result->created > 0) {
                        $notification->success();
                    } else {
                        $notification->danger();
                    }
                    $notification->send();
                }),
            Actions\Action::make('prevalidarSeguimiento')
                ->label('Prevalidar Excel seguimiento')
                ->icon('heroicon-o-beaker')
                ->visible(fn (): bool => (bool) auth()->user()?->canManageDirectorCatalogs())
                ->form([
                    FileUpload::make('archivo')
                        ->label('Archivo Excel (seguimiento/manual)')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/vnd.ms-excel',
                        ])
                        ->disk('local')
                        ->directory('imports')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $relativePath = (string) ($data['archivo'] ?? '');
                    $filePath = Storage::disk('local')->path($relativePath);
                    $result = app(ProjectBulkImportService::class)
                        ->importFromSpreadsheet($filePath, (int) auth()->id(), true);

                    $body = "Válidas: {$result->created} | Con error: {$result->skipped} | Advertencias: {$result->warnings}";
                    if (!empty($result->messages)) {
                        $top = array_slice($result->messages, 0, 15);
                        $body .= "\n" . collect($top)
                                ->map(fn (array $m): string => "Fila {$m['line']} [{$m['type']}]: {$m['message']}")
                                ->implode("\n");
                        if (count($result->messages) > 15) {
                            $body .= "\n... y " . (count($result->messages) - 15) . " más.";
                        }
                    }

                    $notification = Notification::make()->title('Prevalidación completada')->body($body);
                    if ($result->skipped === 0) {
                        $notification->success();
                    } else {
                        $notification->warning();
                    }
                    $notification->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
