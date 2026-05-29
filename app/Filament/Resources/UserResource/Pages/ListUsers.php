<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Role;
use App\Services\UserBulkImportService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadUsersTemplate')
                ->label('Descargar plantilla Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $spreadsheet = new Spreadsheet();
                    $sheet = $spreadsheet->getActiveSheet();
                    $sheet->setTitle('users_import');

                    $headers = ['name', 'email', 'password', 'role_slug'];
                    $required = [
                        'name' => 'Requerido',
                        'email' => 'Requerido',
                        'password' => 'Requerido',
                        'role_slug' => 'Opcional (default: consulta)',
                    ];
                    foreach ($headers as $idx => $header) {
                        $col = Coordinate::stringFromColumnIndex($idx + 1);
                        $sheet->setCellValue("{$col}1", $header);
                        $sheet->setCellValue("{$col}2", $required[$header] ?? 'Opcional');
                    }

                    $example = ['Jonathan Jimenez', 'usuario@aim-meta.gov.co', 'Temporal123*', 'formulador'];
                    foreach ($example as $idx => $value) {
                        $col = Coordinate::stringFromColumnIndex($idx + 1);
                        $sheet->setCellValue("{$col}3", $value);
                    }
                    $sheet->setCellValue('A4', 'Tip: role_slug válidos en pestaña catalogos_id.');

                    $help = $spreadsheet->createSheet();
                    $help->setTitle('catalogos_id');
                    $help->setCellValue('A1', 'Roles disponibles (usar role_slug)');
                    $help->setCellValue('A3', 'id');
                    $help->setCellValue('B3', 'name');
                    $help->setCellValue('C3', 'slug');

                    $r = 4;
                    foreach (Role::query()->orderBy('name')->get(['id', 'name', 'slug']) as $role) {
                        $help->setCellValue("A{$r}", (int) $role->id);
                        $help->setCellValue("B{$r}", (string) $role->name);
                        $help->setCellValue("C{$r}", (string) $role->slug);
                        $r++;
                    }

                    $tmpPath = storage_path('app/tmp/plantilla_carga_masiva_usuarios.xlsx');
                    if (!is_dir(dirname($tmpPath))) {
                        mkdir(dirname($tmpPath), 0775, true);
                    }
                    $writer = new Xlsx($spreadsheet);
                    $writer->save($tmpPath);

                    return response()->download($tmpPath, 'plantilla_carga_masiva_usuarios.xlsx')->deleteFileAfterSend(true);
                }),
            Actions\Action::make('bulkImportUsers')
                ->label('Cargar Excel de usuarios')
                ->icon('heroicon-o-arrow-up-tray')
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
                    $result = app(UserBulkImportService::class)
                        ->importFromSpreadsheet($filePath, (int) auth()->id());

                    $body = "Creados: {$result->created} | Omitidos: {$result->skipped} | Advertencias: {$result->warnings}";
                    $body .= " | Correos enviados: {$result->emailsSent} | Fallos correo: {$result->emailsFailed}";
                    if (!empty($result->messages)) {
                        $top = array_slice($result->messages, 0, 10);
                        $body .= "\n" . collect($top)
                                ->map(fn (array $m): string => "Fila {$m['line']} [{$m['type']}]: {$m['message']}")
                                ->implode("\n");
                        if (count($result->messages) > 10) {
                            $body .= "\n... y " . (count($result->messages) - 10) . " más.";
                        }
                    }

                    $notification = Notification::make()->title('Carga masiva de usuarios finalizada')->body($body);
                    if ($result->created > 0) {
                        $notification->success();
                    } else {
                        $notification->danger();
                    }
                    $notification->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
