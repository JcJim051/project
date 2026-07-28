<?php

namespace App\Services;

use App\Models\DocumentTemplate;
use App\Models\Project;
use App\Models\ProjectBankRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProjectBankRequestService
{
    private const VARIANTS = [
        'obra' => [
            'sheet' => ' OBRA',
            'date' => 'A4',
            'recipient' => ['A8', 'A9', 'A10', 'A11'],
            'subject' => 'A15',
            'project_name' => 'G25',
            'project_id' => 'Y25',
            'dependency' => 'G29',
            'dependency_code' => 'Y29',
            'period' => 'A36',
            'activity_start' => 37,
            'activity_end' => 52,
            'object' => 'A56',
            'total_value' => 'T56',
            'beneficiaries_total' => 'G59',
            'beneficiaries_rural' => 'L58',
            'beneficiaries_urban' => 'L60',
            'beneficiary_description' => 'Q58',
            'other_results' => 'G62',
            'tracer_row' => 68,
            'differential_start' => 75,
            'pertinence' => 'A86',
            'legal_framework' => 'A90',
            'market_study' => 'A94',
            'observations' => 'A96',
            'signatory_base' => 103,
        ],
        'inter' => [
            'sheet' => ' INTER',
            'date' => 'A4',
            'recipient' => ['A7', 'A8', 'A9', 'A10'],
            'subject' => 'A14',
            'project_name' => 'G22',
            'project_id' => 'Y22',
            'dependency' => 'G26',
            'dependency_code' => 'Y26',
            'period' => 'A33',
            'activity_start' => 34,
            'activity_end' => 34,
            'object' => 'A39',
            'total_value' => 'T39',
            'beneficiaries_total' => 'G42',
            'beneficiaries_rural' => 'L41',
            'beneficiaries_urban' => 'L43',
            'beneficiary_description' => 'Q41',
            'other_results' => 'G45',
            'tracer_row' => 50,
            'differential_start' => 57,
            'pertinence' => 'A67',
            'legal_framework' => 'A71',
            'market_study' => 'A75',
            'observations' => 'A78',
            'signatory_base' => 84,
        ],
        'apoyo' => [
            'sheet' => 'APOYO',
            'date' => 'A4',
            'recipient' => ['A6', 'A7', 'A8', 'A9'],
            'subject' => 'A13',
            'project_name' => 'G21',
            'project_id' => 'Y21',
            'dependency' => 'G25',
            'dependency_code' => 'Y25',
            'period' => 'A32',
            'activity_start' => 33,
            'activity_end' => 33,
            'object' => 'A37',
            'total_value' => 'T37',
            'beneficiaries_total' => 'G40',
            'beneficiaries_rural' => 'L39',
            'beneficiaries_urban' => 'L41',
            'beneficiary_description' => 'Q39',
            'other_results' => 'G43',
            'tracer_row' => 48,
            'differential_start' => 55,
            'pertinence' => 'A66',
            'legal_framework' => 'A70',
            'market_study' => 'A74',
            'observations' => 'A77',
            'signatory_base' => 83,
        ],
    ];

    public function create(Project $project, array $data, ?int $userId): array
    {
        $variant = (string) $data['variant'];
        if (! isset(self::VARIANTS[$variant])) {
            throw new \RuntimeException('La modalidad de solicitud no es válida.');
        }

        $template = $this->activeTemplate();
        $versionNumber = (int) ProjectBankRequest::query()
            ->where('project_id', $project->id)
            ->where('variant', $variant)
            ->max('version_number') + 1;

        $requestRecord = DB::transaction(function () use ($project, $data, $userId, $template, $variant, $versionNumber) {
            return ProjectBankRequest::query()->create([
                'project_id' => $project->id,
                'document_template_id' => $template?->id,
                'created_by_user_id' => $userId,
                'variant' => $variant,
                'version_number' => $versionNumber,
                'generation_type' => $data['generation_type'],
                'status' => 'generating',
                'form_data' => $data,
                'update_reason' => $data['update_reason'] ?? null,
            ]);
        });

        try {
            $path = $this->generate($project, $requestRecord, $template);
            $filename = Str::slug((string) $project->nombre, '_')
                .'_F-BS-01_'.strtoupper($variant).'_V'.$versionNumber.'.xlsx';
            $requestRecord->forceFill([
                'status' => 'generated',
                'output_filename' => $filename,
                'generated_at' => now(),
            ])->save();

            return ['record' => $requestRecord->fresh(), 'path' => $path, 'filename' => $filename];
        } catch (\Throwable $exception) {
            $requestRecord->forceFill(['status' => 'failed'])->save();
            foreach (glob(storage_path('app/tmp/bank_requests/'.$requestRecord->id.'-*')) ?: [] as $directory) {
                File::deleteDirectory($directory);
            }
            throw $exception;
        }
    }

    public function activeTemplate(): ?DocumentTemplate
    {
        return DocumentTemplate::query()
            ->where('template_type', 'bank_request_fbs01')
            ->where('file_kind', 'xlsx')
            ->where('is_active', true)
            ->orderByDesc('effective_at')
            ->orderByDesc('id')
            ->first();
    }

    public function templatePath(?DocumentTemplate $template): string
    {
        if ($template?->ruta_archivo && Storage::disk('local')->exists($template->ruta_archivo)) {
            return Storage::disk('local')->path($template->ruta_archivo);
        }

        $fallback = resource_path('templates/bank/fbs01_v07.xlsx');
        if (is_file($fallback)) {
            return $fallback;
        }

        throw new \RuntimeException('No hay una plantilla activa para la solicitud F-BS-01.');
    }

    private function generate(Project $project, ProjectBankRequest $requestRecord, ?DocumentTemplate $template): string
    {
        $map = self::VARIANTS[$requestRecord->variant];
        $data = $requestRecord->form_data;
        $sourcePath = $this->templatePath($template);
        $tmpDir = storage_path('app/tmp/bank_requests/'.$requestRecord->id.'-'.Str::uuid());
        File::ensureDirectoryExists($tmpDir);
        $outputPath = $tmpDir.'/request.xlsx';

        $spreadsheet = IOFactory::load($sourcePath);
        $configuredSheet = data_get($template?->sheet_config, $requestRecord->variant);
        $sheet = $spreadsheet->getSheetByName((string) $configuredSheet)
            ?: $spreadsheet->getSheetByName($map['sheet']);
        if (! $sheet) {
            throw new \RuntimeException('La plantilla no contiene la hoja de modalidad '.strtoupper($requestRecord->variant).'.');
        }
        $spreadsheet->setActiveSheetIndex($spreadsheet->getIndex($sheet));

        $project->loadMissing(['producto', 'municipios', 'bankProfile', 'bankActivityRows', 'bankSignatories']);
        $profile = $project->bankProfile;
        $activities = $this->activitiesForVariant($project->bankActivityRows, $requestRecord->variant);

        $sheet->setCellValue($map['date'], 'Villavicencio, '.Carbon::parse($data['request_date'])->locale('es')->translatedFormat('d \\d\\e F \\d\\e Y'));
        foreach ($map['recipient'] as $index => $cell) {
            $sheet->setCellValue($cell, [
                $data['recipient_salutation'] ?? 'Doctora',
                $data['recipient_name'],
                $data['recipient_title'],
                $data['recipient_entity'],
            ][$index] ?? '');
        }
        $sheet->setCellValue($map['subject'], 'ASUNTO: '.$data['subject']);
        $sheet->setCellValue($map['project_name'], $project->nombre);
        $sheet->setCellValueExplicit($map['project_id'], (string) $project->id_proyecto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->setCellValue($map['dependency'], $profile?->dependencia ?: 'AGENCIA PARA LA INFRAESTRUCTURA DEL META');
        $sheet->setCellValue($map['dependency_code'], $profile?->codigo_dependencia ?: '26');
        $sheet->setCellValue($map['period'], 'VIGENCIA '.$this->periodLabel($project, $profile));

        $this->clearActivityRows($sheet, $map['activity_start'], $map['activity_end']);
        foreach ($activities->take($map['activity_end'] - $map['activity_start'] + 1)->values() as $index => $activity) {
            $row = $map['activity_start'] + $index;
            $value = (float) ($activity->valor_actividad ?? 0);
            $sheet->setCellValue("A{$row}", $activity->producto_mga ?: $project->producto?->nombre_con_codigo);
            $sheet->setCellValue("C{$row}", $activity->actividad);
            $sheet->setCellValue("G{$row}", $value);
            $sheet->setCellValue("K{$row}", $profile?->codigo_fuente);
            $sheet->setCellValue("M{$row}", $profile?->nombre_fuente);
            $sheet->setCellValue("Q{$row}", $profile?->meta_plan_codigo);
            $sheet->setCellValue("T{$row}", $profile?->meta_plan_nombre);
            $sheet->setCellValue("AA{$row}", $value);
        }

        $sheet->setCellValue($map['object'], $data['expense_object']);
        $sheet->setCellValue($map['total_value'], (float) $data['value_to_certify']);
        $sheet->setCellValue($map['beneficiaries_total'], (int) $data['beneficiaries_total']);
        $sheet->setCellValue($map['beneficiaries_rural'], (int) $data['beneficiaries_rural']);
        $sheet->setCellValue($map['beneficiaries_urban'], (int) $data['beneficiaries_urban']);
        $sheet->setCellValue($map['beneficiary_description'], $data['beneficiary_description']);
        $sheet->setCellValue($map['other_results'], $data['other_results']);
        $this->writeTracer($sheet, $map['tracer_row'], $data['budget_tracer']);
        $this->writeDifferential($sheet, $map['differential_start'], $data['differential'] ?? []);
        $sheet->setCellValue($map['pertinence'], $data['pertinence']);
        $sheet->setCellValue($map['legal_framework'], $data['legal_framework']);
        $sheet->setCellValue($map['market_study'], $data['market_study']);
        $sheet->setCellValue($map['observations'], $data['observations'] ?? '');

        $signatories = $project->bankSignatories->keyBy('role');
        $baseRow = $map['signatory_base'];
        $ordenador = $signatories->get('formulador_oficial');
        $elaboro = $signatories->get('aprobo') ?: $signatories->get('elaboro');
        foreach ([
            ["D{$baseRow}", $ordenador?->nombre],
            ['D'.($baseRow + 1), $ordenador?->cargo],
            ['D'.($baseRow + 2), $ordenador?->correo],
            ['D'.($baseRow + 3), $ordenador?->telefono],
            ["T{$baseRow}", $elaboro?->nombre],
            ['T'.($baseRow + 1), $elaboro?->cargo],
            ['T'.($baseRow + 2), $elaboro?->correo],
            ['T'.($baseRow + 3), $elaboro?->telefono],
        ] as [$cell, $value]) {
            if (filled($value)) {
                $sheet->setCellValue($cell, $value);
            }
        }

        $this->sanitizeBrokenDefinedNames($spreadsheet);
        IOFactory::createWriter($spreadsheet, 'Xlsx')->save($outputPath);

        return $outputPath;
    }

    private function activitiesForVariant($activities, string $variant)
    {
        return $activities->filter(function ($activity) use ($variant) {
            $name = Str::upper(Str::ascii((string) $activity->actividad));

            return match ($variant) {
                'inter' => str_contains($name, 'INTERVENTOR'),
                'apoyo' => str_contains($name, 'APOYO'),
                default => ! str_contains($name, 'INTERVENTOR') && ! str_contains($name, 'APOYO'),
            };
        })->values();
    }

    private function periodLabel(Project $project, $profile): string
    {
        $years = $project->executionYears()->pluck('anio')->filter()->sort()->values();
        if ($years->isEmpty()) {
            $years = collect([
                $profile?->horizonte_anio_0,
                $profile?->horizonte_anio_1,
                $profile?->horizonte_anio_2,
                $profile?->horizonte_anio_3,
            ])->filter()->sort()->values();
        }

        return $years->isEmpty()
            ? (string) now()->year
            : ($years->count() === 1 ? (string) $years->first() : $years->first().'-'.$years->last());
    }

    private function clearActivityRows($sheet, int $start, int $end): void
    {
        foreach (range($start, $end) as $row) {
            foreach (['A', 'C', 'G', 'K', 'M', 'Q', 'T', 'AA'] as $column) {
                $sheet->setCellValue("{$column}{$row}", null);
            }
        }
    }

    private function writeTracer($sheet, int $row, string $value): void
    {
        $labels = [
            'narp' => ["A{$row}", 'N.A.R.P'],
            'indigenas' => ["E{$row}", 'INDÍGENAS'],
            'mujer' => ["J{$row}", 'EQUIDAD DE LA MUJER'],
            'no_aplica' => ["S{$row}", 'NO APLICA'],
        ];
        foreach ($labels as $key => [$cell, $label]) {
            $sheet->setCellValue($cell, $label.($key === $value ? '  X' : ''));
        }
        $sheet->setCellValue("Y{$row}", $value === 'no_aplica' ? 'X' : null);
    }

    private function writeDifferential($sheet, int $startRow, array $rows): void
    {
        $columns = [
            'men' => 'E',
            'women' => 'G',
            'lgbti' => 'I',
            'victim' => 'N',
            'disability' => 'P',
            'afro' => 'R',
            'indigenous' => 'T',
            'extreme_poverty' => 'V',
            'other' => 'Y',
        ];
        foreach (array_values($rows) as $index => $rowData) {
            if ($index > 3) {
                break;
            }
            foreach ($columns as $key => $column) {
                $sheet->setCellValue($column.($startRow + $index), (int) ($rowData[$key] ?? 0));
            }
        }
    }

    private function sanitizeBrokenDefinedNames($spreadsheet): void
    {
        foreach ($spreadsheet->getDefinedNames() as $definedName) {
            $value = (string) $definedName->getValue();
            if (str_contains($value, '#REF!') || preg_match('/\\[[^\\]]+\\]/', $value)) {
                $spreadsheet->removeDefinedName($definedName->getName(), $definedName->getScope());
            }
        }
    }
}
