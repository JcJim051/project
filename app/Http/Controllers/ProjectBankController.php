<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectBankRequest;
use App\Models\ProjectWorkflowStep;
use App\Models\RequirementEvidence;
use App\Services\GoogleDriveService;
use App\Services\ProjectBankExcelService;
use App\Services\ProjectBankRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class ProjectBankController extends Controller
{
    public function storeBankRequest(
        Request $request,
        Project $project,
        ProjectBankRequestService $service,
        GoogleDriveService $drive
    ) {
        $this->authorizeProjectAccess($request, $project, true);

        $data = $request->validate([
            'variant' => ['required', 'in:obra,inter,apoyo'],
            'generation_type' => ['required', 'in:initial,update'],
            'update_reason' => ['nullable', 'required_if:generation_type,update', 'string', 'max:2000'],
            'request_date' => ['required', 'date'],
            'recipient_salutation' => ['nullable', 'string', 'max:60'],
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_title' => ['required', 'string', 'max:255'],
            'recipient_entity' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:500'],
            'expense_object' => ['required', 'string', 'max:4000'],
            'value_to_certify' => ['required', 'numeric', 'min:0'],
            'beneficiaries_total' => ['required', 'integer', 'min:0'],
            'beneficiaries_rural' => ['required', 'integer', 'min:0'],
            'beneficiaries_urban' => ['required', 'integer', 'min:0'],
            'beneficiary_description' => ['required', 'string', 'max:5000'],
            'other_results' => ['required', 'string', 'max:5000'],
            'budget_tracer' => ['required', 'in:narp,indigenas,mujer,no_aplica'],
            'differential' => ['nullable', 'array', 'max:4'],
            'differential.*' => ['array'],
            'differential.*.*' => ['nullable', 'integer', 'min:0'],
            'pertinence' => ['required', 'string', 'max:10000'],
            'legal_framework' => ['required', 'string', 'max:15000'],
            'market_study' => ['required', 'string', 'max:15000'],
            'observations' => ['nullable', 'string', 'max:10000'],
        ], [
            'update_reason.required_if' => 'Indica el motivo de la actualización.',
            'value_to_certify.required' => 'Indica el valor que se solicita certificar.',
        ]);

        try {
            $generated = $service->create($project, $data, $request->user()->id);
        } catch (\Throwable $exception) {
            return back()->withErrors(['bank_request' => $exception->getMessage()])->withInput();
        }

        /** @var ProjectBankRequest $record */
        $record = $generated['record'];
        $path = $generated['path'];
        if ($project->drive_folder_id && $drive->isAuthorized($request->user()->id)) {
            try {
                $uploaded = $drive->uploadLocalFileToFolder(
                    (string) $project->drive_folder_id,
                    $generated['filename'],
                    $path,
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    $request->user()->id
                );
                $record->forceFill([
                    'drive_folder_id' => $project->drive_folder_id,
                    'drive_file_id' => $uploaded['id'] ?? null,
                ])->save();
                $this->linkGeneratedRequestToWorkflow(
                    $project,
                    $record,
                    $uploaded,
                    $request->user()->id
                );
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        register_shutdown_function(static function () use ($path): void {
            File::deleteDirectory(dirname($path));
        });

        return response()->download($path, $generated['filename'])->deleteFileAfterSend(true);
    }

    public function downloadBankRequest(
        Request $request,
        Project $project,
        ProjectBankRequest $bankRequest,
        GoogleDriveService $drive
    ) {
        abort_unless((int) $bankRequest->project_id === (int) $project->id, 404);
        $this->authorizeProjectAccess($request, $project, false);
        abort_unless($bankRequest->drive_file_id, 404, 'La versión histórica no tiene archivo disponible en Drive.');

        $tmpDir = storage_path('app/tmp/bank_requests/downloads/'.Str::uuid());
        File::ensureDirectoryExists($tmpDir);
        $path = $tmpDir.'/'.($bankRequest->output_filename ?: 'solicitud-fbs01.xlsx');
        $drive->downloadFile((string) $bankRequest->drive_file_id, $path, $request->user()->id);
        register_shutdown_function(static function () use ($tmpDir): void {
            File::deleteDirectory($tmpDir);
        });

        return response()->download($path, basename($path))->deleteFileAfterSend(true);
    }

    private function linkGeneratedRequestToWorkflow(
        Project $project,
        ProjectBankRequest $bankRequest,
        array $uploaded,
        int $userId
    ): void {
        $step = ProjectWorkflowStep::query()
            ->where('slug', 'solicitud-del-banco-de-programas-y-proyectos')
            ->whereHas('stage', fn ($query) => $query
                ->where('funding_source', $project->funding_source ?: 'sgr')
                ->where('is_active', true))
            ->with('requirementLinks.requirement')
            ->first();
        $requirement = $step?->requirementLinks->first()?->requirement;
        $driveFileId = (string) ($uploaded['id'] ?? '');
        if (! $requirement || $driveFileId === '') {
            return;
        }

        $project->requisitos()->syncWithoutDetaching([
            $requirement->id => ['activated_at' => now()],
        ]);
        RequirementEvidence::query()->updateOrCreate(
            [
                'project_id' => $project->id,
                'drive_file_id' => $driveFileId,
            ],
            [
                'requirement_id' => $requirement->id,
                'drive_file_name' => $bankRequest->output_filename,
                'drive_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'drive_modified_time' => now(),
                'drive_folder_name' => $requirement->carpeta,
                'source' => 'generated_bank_request',
                'linked_by_user_id' => $userId,
                'linked_at' => now(),
                'link_note' => 'Solicitud F-BS-01 '.strtoupper($bankRequest->variant).' V'.$bankRequest->version_number,
                'in_drive' => true,
            ]
        );
    }

    private function authorizeProjectAccess(Request $request, Project $project, bool $mutate): void
    {
        $user = $request->user();
        $hasGlobalAccess = (bool) ($user?->isAdminUser() || $user?->hasAnyRole(['director', 'formulador_maestro']));
        $isAssigned = $user && in_array((int) $user->id, [
            (int) $project->formulador_id,
            (int) $project->estructurador_id,
        ], true);

        abort_unless($hasGlobalAccess || $isAssigned, 403);
        if ($mutate) {
            abort_unless($user?->canMutateProjects(), 403);
        }
    }

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

        $name = Str::slug((string) $project->nombre, '_').'_'.$suffix.'.xlsx';

        return response()->download($path, $name)->deleteFileAfterSend(true);
    }

    public function downloadZip(Project $project, ProjectBankExcelService $service)
    {
        $types = ['bank_plan_inversion', 'bank_plan_desarrollo', 'bank_cronograma'];
        $tmpDir = storage_path('app/tmp/bank_excels');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0775, true);
        }

        $zipPath = $tmpDir.'/'.Str::uuid()->toString().'.zip';
        $zip = new ZipArchive;
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
                $zip->addFile($path, Str::slug((string) $project->nombre, '_').'_'.$suffix.'.xlsx');
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

        return response()->download($zipPath, Str::slug((string) $project->nombre, '_').'_banco_excel.zip')
            ->deleteFileAfterSend(true);
    }
}
