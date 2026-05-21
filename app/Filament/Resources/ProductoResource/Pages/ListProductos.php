<?php

namespace App\Filament\Resources\ProductoResource\Pages;

use App\Filament\Resources\ProductoResource;
use App\Models\Producto;
use App\Models\Sector;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListProductos extends ListRecords
{
    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargarTodo')
                ->label('Descargar todo')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $payload = Producto::query()
                        ->orderBy('id')
                        ->get()
                        ->map(function (Producto $item): array {
                            return [
                                'sector_id' => $item->sector_id,
                                'sector_codigo' => $item->sector?->codigo,
                                'codigo' => $item->codigo,
                                'nombre' => $item->nombre,
                                'activo' => (bool) $item->activo,
                            ];
                        })
                        ->all();

                    return response()->streamDownload(function () use ($payload): void {
                        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }, 'productos_mga_export.json', ['Content-Type' => 'application/json']);
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

                        $sectorId = isset($row['sector_id']) ? (int) $row['sector_id'] : 0;
                        $sectorCode = trim((string) ($row['sector_codigo'] ?? ''));
                        if ($sectorCode !== '') {
                            $sectorId = (int) (Sector::query()->where('codigo', $sectorCode)->value('id') ?? $sectorId);
                        }
                        $codigo = trim((string) ($row['codigo'] ?? ''));
                        $nombre = trim((string) ($row['nombre'] ?? ''));
                        if ($sectorId <= 0 || $codigo === '' || $nombre === '') {
                            continue;
                        }

                        Producto::query()->updateOrCreate(
                            ['sector_id' => $sectorId, 'codigo' => $codigo],
                            [
                                'nombre' => $nombre,
                                'activo' => (bool) ($row['activo'] ?? true),
                            ]
                        );
                        $count++;
                    }

                    Notification::make()->success()->title('Importacion completada')->body("Productos procesados: {$count}")->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}
