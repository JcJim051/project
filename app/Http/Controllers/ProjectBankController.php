<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\ProjectBankExcelService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ZipArchive;

class ProjectBankController extends Controller
{
    public function updateProfile(Request $request, Project $project, ProjectBankExcelService $service)
    {
        $data = $request->validate([
            'horizonte_anio_0' => ['nullable', 'integer', 'between:2000,2100'],
            'horizonte_anio_1' => ['nullable', 'integer', 'between:2000,2100'],
            'horizonte_anio_2' => ['nullable', 'integer', 'between:2000,2100'],
            'horizonte_anio_3' => ['nullable', 'integer', 'between:2000,2100'],
            'tipo_presentacion' => ['required', 'in:programa,proyecto'],
            'tipo_tramite' => ['required', 'in:nuevo,actualizacion'],
            'codigo_dependencia' => ['nullable', 'string', 'max:30'],
            'dependencia' => ['nullable', 'string', 'max:255'],
            'vigencia' => ['nullable', 'integer', 'between:2000,2100'],
            'proyecto_titulo_override' => ['nullable', 'string', 'max:500'],
            'pilar' => ['nullable', 'string', 'max:255'],
            'eje' => ['nullable', 'string', 'max:500'],
            'linea' => ['nullable', 'string', 'max:500'],
            'programa' => ['nullable', 'string', 'max:500'],
            'subprograma' => ['nullable', 'string', 'max:500'],
            'codigo_fuente' => ['nullable', 'string', 'max:80'],
            'nombre_fuente' => ['nullable', 'string', 'max:255'],
            'meta_plan_codigo' => ['nullable', 'string', 'max:100'],
            'meta_plan_nombre' => ['nullable', 'string', 'max:500'],
            'municipio_relacion' => ['nullable', 'string', 'max:255'],
            'beneficiarios' => ['nullable', 'integer', 'min:0'],
            'sector_texto_plantilla' => ['nullable', 'string', 'max:255'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $service->saveProfile($project, $data);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Perfil Banco actualizado.',
            ]);
        }

        return back()->with('status', 'Perfil Banco actualizado.');
    }

    public function updateSignatories(Request $request, Project $project, ProjectBankExcelService $service)
    {
        $rows = $request->input('rows', []);
        if (! is_array($rows)) {
            return back()->withErrors(['rows' => 'Formato de firmantes inválido.']);
        }

        foreach ($rows as $index => $row) {
            $request->validate([
                "rows.$index.role" => ['nullable', 'string', 'max:80'],
                "rows.$index.orden" => ['nullable', 'integer', 'min:0', 'max:999'],
                "rows.$index.nombre" => ['nullable', 'string', 'max:255'],
                "rows.$index.cargo" => ['nullable', 'string', 'max:255'],
                "rows.$index.correo" => ['nullable', 'email', 'max:255'],
                "rows.$index.telefono" => ['nullable', 'string', 'max:60'],
            ]);
        }

        $service->replaceSignatories($project, $rows);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Firmantes actualizados.',
            ]);
        }

        return back()->with('status', 'Firmantes actualizados.');
    }

    public function updateFinancingRows(Request $request, Project $project, ProjectBankExcelService $service)
    {
        // Las filas de financiación heredan metadatos generales del perfil y no se editan por fila.
        $service->ensureSeeded($project);

        return back()->with('status', 'Filas de financiación sincronizadas desde Cadena de Valor.');
    }

    public function updateActivityRows(Request $request, Project $project, ProjectBankExcelService $service)
    {
        $rows = $request->input('rows', []);
        if (! is_array($rows)) {
            return back()->withErrors(['rows' => 'Formato de actividades inválido.']);
        }

        foreach ($rows as $index => $row) {
            $request->validate([
                "rows.$index.orden" => ['nullable', 'integer', 'min:0', 'max:999'],
                "rows.$index.producto_mga" => ['nullable', 'string', 'max:255'],
                "rows.$index.actividad" => ['required', 'string', 'max:500'],
                "rows.$index.valor_actividad" => ['nullable', 'numeric', 'min:0'],
            ]);
        }

        $service->replaceActivityRows($project, $rows);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Filas de actividades actualizadas.',
            ]);
        }

        return back()->with('status', 'Filas de actividades actualizadas.');
    }

    public function downloadExcel(Project $project, string $templateType, ProjectBankExcelService $service)
    {
        $allowed = ['bank_plan_inversion', 'bank_plan_desarrollo', 'bank_cronograma'];
        if (! in_array($templateType, $allowed, true)) {
            abort(404);
        }

        try {
            $path = $service->generateExcel($project, $templateType);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['bank_excel' => $e->getMessage()]);
        }

        $suffix = match ($templateType) {
            'bank_plan_inversion' => 'F-PE-23',
            'bank_plan_desarrollo' => 'F-PE-24',
            'bank_cronograma' => 'F-PE-25',
            default => 'BANCO',
        };

        $name = Str::slug((string) $project->nombre, '_') . '_' . $suffix . '.xlsx';

        return response()->download($path, $name)->deleteFileAfterSend(true);
    }

    public function downloadZip(Project $project, ProjectBankExcelService $service)
    {
        $types = ['bank_plan_inversion', 'bank_plan_desarrollo', 'bank_cronograma'];
        $tmpDir = storage_path('app/tmp/bank_excels');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $zipPath = $tmpDir . '/' . Str::uuid()->toString() . '.zip';
        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE);
        $generated = [];

        try {
            foreach ($types as $type) {
                $path = $service->generateExcel($project, $type);
                $suffix = match ($type) {
                    'bank_plan_inversion' => 'F-PE-23',
                    'bank_plan_desarrollo' => 'F-PE-24',
                    'bank_cronograma' => 'F-PE-25',
                    default => 'BANCO',
                };
                $zip->addFile($path, Str::slug((string) $project->nombre, '_') . '_' . $suffix . '.xlsx');
                $generated[] = $path;
            }
        } catch (\RuntimeException $e) {
            $zip->close();
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            foreach ($generated as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
            return back()->withErrors(['bank_excel' => $e->getMessage()]);
        }

        $zip->close();

        foreach ($generated as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }

        return response()->download($zipPath, Str::slug((string) $project->nombre, '_') . '_banco_excel.zip')
            ->deleteFileAfterSend(true);
    }
}
