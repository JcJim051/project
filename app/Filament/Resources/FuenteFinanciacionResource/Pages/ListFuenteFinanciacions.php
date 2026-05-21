<?php

namespace App\Filament\Resources\FuenteFinanciacionResource\Pages;

use App\Filament\Resources\FuenteFinanciacionResource;
use App\Models\FuenteFinanciacion;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

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
                        ->get(['codigo', 'nombre', 'activo'])
                        ->toArray();

                    return response()->streamDownload(function () use ($payload): void {
                        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }, 'fuentes_export.json', ['Content-Type' => 'application/json']);
                }),
            Actions\Action::make('importar')
                ->label('Importar')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    FileUpload::make('archivo')
                        ->label('Archivo JSON')
                        ->acceptedFileTypes(['application/json', 'text/plain'])
                        ->disk('local')
                        ->directory('imports')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $relativePath = (string) ($data['archivo'] ?? '');
                    $filePath = Storage::disk('local')->path($relativePath);
                    $decoded = json_decode((string) file_get_contents($filePath), true);

                    if (! is_array($decoded)) {
                        Notification::make()->danger()->title('Archivo invalido')->body('El JSON no tiene formato valido.')->send();
                        return;
                    }

                    $count = 0;
                    foreach ($decoded as $row) {
                        if (! is_array($row)) {
                            continue;
                        }
                        $codigo = trim((string) ($row['codigo'] ?? ''));
                        $nombre = trim((string) ($row['nombre'] ?? ''));
                        if ($codigo === '' || $nombre === '') {
                            continue;
                        }

                        FuenteFinanciacion::query()->updateOrCreate(
                            ['codigo' => $codigo],
                            [
                                'nombre' => $nombre,
                                'activo' => (bool) ($row['activo'] ?? true),
                            ]
                        );
                        $count++;
                    }

                    Notification::make()->success()->title('Importación completada')->body("Fuentes procesadas: {$count}")->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}

