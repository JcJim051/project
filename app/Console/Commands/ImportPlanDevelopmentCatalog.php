<?php

namespace App\Console\Commands;

use App\Models\PlanDevelopmentCatalogItem;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportPlanDevelopmentCatalog extends Command
{
    protected $signature = 'bank:import-plan-catalog {path? : Ruta del archivo xlsx}';

    protected $description = 'Importa catálogo Pilar/Eje/Línea/Programa/Subprograma/Meta para Banco Excel.';

    public function handle(): int
    {
        $defaultPath = '/Users/jonathanjimenez/Downloads/CODIFICACIÓN PLAN DE DESARROLLO 2024-2027 ok (1).xlsx';
        $path = (string) ($this->argument('path') ?: $defaultPath);

        if (! is_file($path)) {
            $this->error('No existe el archivo: ' . $path);
            return self::FAILURE;
        }

        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getSheet(0);

        $current = [
            'pilar_codigo' => null,
            'pilar' => null,
            'eje_codigo' => null,
            'eje' => null,
            'linea_codigo' => null,
            'linea' => null,
            'programa_codigo' => null,
            'programa' => null,
            'subprograma_codigo' => null,
            'subprograma' => null,
            'sector_codigo' => null,
            'sector' => null,
        ];

        $rows = [];
        $highest = $sheet->getHighestRow();

        for ($r = 1; $r <= $highest; $r++) {
            $codigo = trim((string) $sheet->getCell('B' . $r)->getFormattedValue());
            $texto = trim((string) $sheet->getCell('C' . $r)->getFormattedValue());

            if ($codigo === '' || $texto === '') {
                continue;
            }

            $upper = mb_strtoupper($texto);

            if (str_contains($upper, 'PILAR')) {
                $current['pilar_codigo'] = $codigo;
                $current['pilar'] = $texto;
                continue;
            }
            if (str_contains($upper, 'EJE ESTRAT')) {
                $current['eje_codigo'] = $codigo;
                $current['eje'] = $texto;
                continue;
            }
            if (str_contains($upper, 'LÍNEA ESTRAT') || str_contains($upper, 'LINEA ESTRAT')) {
                $current['linea_codigo'] = $codigo;
                $current['linea'] = $texto;
                continue;
            }
            if (str_contains($upper, 'SUBPROGRAMA')) {
                $current['subprograma_codigo'] = $codigo;
                $current['subprograma'] = $texto;
                continue;
            }
            if (preg_match('/\\bPROGRAMA\\b/u', $upper)) {
                $current['programa_codigo'] = $codigo;
                $current['programa'] = $texto;
                continue;
            }

            if (preg_match('/SECTOR\s*(\d+)/iu', $texto, $m)) {
                $current['sector_codigo'] = (string) $m[1];
                $current['sector'] = $texto;
                continue;
            }

            if ($current['subprograma_codigo'] === null || $current['programa_codigo'] === null) {
                continue;
            }

            $rows[] = array_merge($current, [
                'codigo_meta_plan' => $codigo,
                'nombre_meta_plan' => $texto,
                'activo' => true,
            ]);
        }

        if (empty($rows)) {
            $this->warn('No se detectaron metas para importar.');
            return self::SUCCESS;
        }

        PlanDevelopmentCatalogItem::query()->delete();

        foreach ($rows as $row) {
            PlanDevelopmentCatalogItem::query()->create($row);
        }

        $this->info('Catálogo importado: ' . count($rows) . ' metas.');

        return self::SUCCESS;
    }
}
