<?php

namespace App\Filament\Resources\MetaResource\Pages;

use App\Filament\Resources\MetaResource;
use App\Models\PlanDevelopmentCatalogItem;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListMetas extends ListRecords
{
    protected static string $resource = MetaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('descargarTodo')
                ->label('Descargar todo')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $payload = PlanDevelopmentCatalogItem::query()
                        ->orderBy('id')
                        ->get()
                        ->map(function (PlanDevelopmentCatalogItem $item): array {
                            return [
                                'sector_codigo' => $item->sector_codigo,
                                'sector' => $item->sector,
                                'pilar_codigo' => $item->pilar_codigo,
                                'pilar' => $item->pilar,
                                'eje_codigo' => $item->eje_codigo,
                                'eje' => $item->eje,
                                'linea_codigo' => $item->linea_codigo,
                                'linea' => $item->linea,
                                'programa_codigo' => $item->programa_codigo,
                                'programa' => $item->programa,
                                'subprograma_codigo' => $item->subprograma_codigo,
                                'subprograma' => $item->subprograma,
                                'codigo_meta_plan' => $item->codigo_meta_plan,
                                'nombre_meta_plan' => $item->nombre_meta_plan,
                                'activo' => (bool) $item->activo,
                            ];
                        })
                        ->all();

                    return response()->streamDownload(function () use ($payload): void {
                        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }, 'metas_export.json', ['Content-Type' => 'application/json']);
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
                        $code = trim((string) ($row['codigo_meta_plan'] ?? ''));
                        $name = trim((string) ($row['nombre_meta_plan'] ?? ''));
                        if ($code === '' || $name === '') {
                            continue;
                        }

                        PlanDevelopmentCatalogItem::query()->updateOrCreate(
                            ['codigo_meta_plan' => $code, 'nombre_meta_plan' => $name],
                            [
                                'sector_codigo' => $row['sector_codigo'] ?? null,
                                'sector' => $row['sector'] ?? null,
                                'pilar_codigo' => $row['pilar_codigo'] ?? null,
                                'pilar' => $row['pilar'] ?? null,
                                'eje_codigo' => $row['eje_codigo'] ?? null,
                                'eje' => $row['eje'] ?? null,
                                'linea_codigo' => $row['linea_codigo'] ?? null,
                                'linea' => $row['linea'] ?? null,
                                'programa_codigo' => $row['programa_codigo'] ?? null,
                                'programa' => $row['programa'] ?? null,
                                'subprograma_codigo' => $row['subprograma_codigo'] ?? null,
                                'subprograma' => $row['subprograma'] ?? null,
                                'activo' => (bool) ($row['activo'] ?? true),
                            ]
                        );
                        $count++;
                    }

                    Notification::make()->success()->title('Importacion completada')->body("Metas procesadas: {$count}")->send();
                }),
            Actions\CreateAction::make(),
        ];
    }
}

