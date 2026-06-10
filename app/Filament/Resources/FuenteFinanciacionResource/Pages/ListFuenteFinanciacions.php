<?php

namespace App\Filament\Resources\FuenteFinanciacionResource\Pages;

use App\Filament\Resources\FuenteFinanciacionResource;
use App\Models\FuenteFinanciacion;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ListFuenteFinanciacions extends ListRecords
{
    protected static string $resource = FuenteFinanciacionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargarTodo')
                ->label('Descargar todo')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $payload = FuenteFinanciacion::query()
                        ->orderBy('id')
                        ->get(['codigo', 'nombre', 'tipo', 'activo'])
                        ->toArray();

                    return response()->streamDownload(function () use ($payload): void {
                        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }, 'fuentes_export.json', ['Content-Type' => 'application/json']);
                }),
            Actions\Action::make('descargarPlantillaExcel')
                ->label('Descargar plantilla Excel')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    return response()->streamDownload(function (): void {
                        $spreadsheet = new Spreadsheet();
                        $sheet = $spreadsheet->getActiveSheet();
                        $sheet->setTitle('fuentes');
                        $sheet->fromArray(['Código', 'Fuente', 'Tipo'], null, 'A1');
                        $sheet->getStyle('A1:C1')->getFont()->setBold(true);
                        $sheet->getColumnDimension('A')->setWidth(22);
                        $sheet->getColumnDimension('B')->setWidth(55);
                        $sheet->getColumnDimension('C')->setWidth(30);

                        (new Xlsx($spreadsheet))->save('php://output');
                        $spreadsheet->disconnectWorksheets();
                    }, 'plantilla_fuentes_financiacion.xlsx', [
                        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    ]);
                }),
            Actions\Action::make('importar')
                ->label('Importar Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('archivo')
                        ->label('Archivo Excel')
                        ->helperText('Encabezados requeridos: Código, Fuente y Tipo.')
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

                    try {
                        $sheet = IOFactory::load($filePath)->getActiveSheet();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->danger()
                            ->title('No se pudo leer el Excel')
                            ->body($exception->getMessage())
                            ->send();
                        return;
                    }

                    $headers = [];
                    foreach ($sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0] ?? [] as $index => $header) {
                        $normalized = Str::of((string) $header)
                            ->ascii()
                            ->lower()
                            ->replaceMatches('/[^a-z0-9]+/', '_')
                            ->trim('_')
                            ->toString();
                        if ($normalized !== '') {
                            $headers[$normalized] = $index;
                        }
                    }

                    if (! isset($headers['codigo'], $headers['fuente'], $headers['tipo'])) {
                        Notification::make()
                            ->danger()
                            ->title('Encabezados incompletos')
                            ->body('El archivo debe incluir las columnas Código, Fuente y Tipo.')
                            ->send();
                        return;
                    }

                    $processed = 0;
                    $skipped = 0;
                    for ($rowNumber = 2; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
                        $row = $sheet->rangeToArray(
                            'A'.$rowNumber.':'.$sheet->getHighestColumn().$rowNumber,
                            null,
                            true,
                            true
                        )[0] ?? [];

                        $codigo = trim((string) ($row[$headers['codigo']] ?? ''));
                        $nombre = trim((string) ($row[$headers['fuente']] ?? ''));
                        $tipo = trim((string) ($row[$headers['tipo']] ?? ''));

                        if ($codigo === '' || $nombre === '' || $tipo === '') {
                            $skipped++;
                            continue;
                        }

                        FuenteFinanciacion::query()->updateOrCreate(
                            ['codigo' => $codigo],
                            [
                                'nombre' => $nombre,
                                'tipo' => $tipo,
                                'activo' => true,
                            ]
                        );
                        $processed++;
                    }

                    Storage::disk('local')->delete($relativePath);

                    Notification::make()
                        ->success()
                        ->title('Importación completada')
                        ->body("Fuentes procesadas: {$processed}. Filas omitidas: {$skipped}.")
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
