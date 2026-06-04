<?php

namespace App\Http\Controllers;

use App\Events\GamificationActivityTriggered;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementEvidence;
use App\Models\AttachmentPackageRun;
use App\Services\GoogleDriveService;
use App\Services\AttachmentPdfRuntime;
use App\Services\MgaTransferAuthorizationService;
use App\Services\RequirementProgressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Carbon\Carbon;

class ProjectManageController extends Controller
{
    public function show(Request $request, Project $project, GoogleDriveService $drive)
    {
        return $this->renderManage($request, $project, $drive, 'projects.manage', 'projects.manage');
    }

    public function showLegacy(Request $request, Project $project, GoogleDriveService $drive)
    {
        return $this->renderManage($request, $project, $drive, 'projects.manage_legacy', 'projects.manage.legacy');
    }

    private function renderManage(Request $request, Project $project, GoogleDriveService $drive, string $viewName, string $routeName)
    {
        $this->expireStaleAttachmentRuns($project->id);

        $project->load(['requisitos', 'sectores', 'formulador', 'estructurador']);
        $requirements = $this->getActiveRequirementsForProject($project);

        $renumerated = $this->buildRenumerationMap($requirements);

        $driveConnected = $drive->isAuthorized(auth()->id());
        $driveReady = $driveConnected && $project->drive_folder_id;
        $syncReport = null;

        if ($driveReady && $request->boolean('sync')) {
            @ini_set('max_execution_time', '0');
            set_time_limit(0);
            try {
                $syncReport = $drive->syncProjectRequirements($project, $requirements, auth()->id());
            } catch (\Throwable $e) {
                return redirect()
                    ->route($routeName, $project)
                    ->with('error', $e->getMessage() ?: 'Error al sincronizar con Drive.');
            }
            if (!empty($syncReport['error'])) {
                return redirect()
                    ->route($routeName, $project)
                    ->with('error', $syncReport['error']);
            }
            if (!$request->boolean('debug')) {
                $syncReport = null;
            }
        }

        $evidenceRows = RequirementEvidence::where('project_id', $project->id)->get();
        $evidences = $evidenceRows->groupBy('requirement_id');

        /** @var RequirementProgressService $progressService */
        $progressService = app(RequirementProgressService::class);
        $progressAnalysis = $progressService->analyze($requirements, $evidenceRows);
        $overallProgress = $progressService->buildOverallProgress($requirements, $progressAnalysis);
        $overallPercent = $overallProgress['percent'];

        $requirementsByFolder = $requirements->groupBy(function ($req) {
            return $req->carpeta ?? 'Sin carpeta';
        });
        $manageSections = $this->buildManageSections($requirementsByFolder);

        $folderProgress = $progressService->buildFolderProgress($requirements, $progressAnalysis);
        $topGroupProgress = $progressService->buildTopGroupProgress(
            $requirements,
            $progressAnalysis,
            fn (string $folder) => $this->detectTopGroupCode($folder)
        );
        $attachmentRuns = AttachmentPackageRun::query()
            ->where('project_id', $project->id)
            ->latest()
            ->limit(8)
            ->get();
        $attachmentPdfHealth = $this->buildAttachmentPdfHealth();
        $attachmentsMinPercent = max(1, min(100, (int) ($project->attachments_min_percent ?? 80)));
        /** @var MgaTransferAuthorizationService $mgaService */
        $mgaService = app(MgaTransferAuthorizationService::class);
        $transferRequest = $mgaService->current($project);
        $canTransferToMga = $mgaService->canTransfer($project, $overallPercent);
        $assigned = collect([
            $project->formulador_id => $project->formulador?->name ?? 'Formulador',
            $project->estructurador_id => $project->estructurador?->name ?? 'Estructurador',
        ])->filter(fn ($name, $id) => !empty($id));
        $transferReceiptStates = [];
        foreach ($assigned as $userId => $name) {
            $receipt = $transferRequest?->receipts?->firstWhere('user_id', (int) $userId);
            $transferReceiptStates[] = [
                'user_id' => (int) $userId,
                'name' => $name,
                'acknowledged' => (bool) $receipt?->acknowledged_at,
                'acknowledged_at' => optional($receipt?->acknowledged_at)->format('Y-m-d H:i'),
            ];
        }
        $currentUserId = (int) auth()->id();
        $canAcknowledgeTransfer = in_array($currentUserId, $assigned->keys()->map(fn ($id) => (int) $id)->all(), true)
            && $transferRequest
            && in_array($transferRequest->status, ['approved', 'rejected'], true);
        $canRequestTransfer = auth()->user()?->canRequestMgaTransfer() === true
            && !$project->transferRequests()->where('status', 'pending')->exists();
        $canAuthorizeTransfer = auth()->user()?->canAuthorizeMgaTransfer() === true;

        return view($viewName, [
            'project' => $project,
            'requirements' => $requirements,
            'requirementsByFolder' => $requirementsByFolder,
            'evidences' => $evidences,
            'driveConnected' => $driveConnected,
            'driveReady' => $driveReady,
            'syncReport' => $syncReport,
            'renumerated' => $renumerated,
            'overallPercent' => $overallPercent,
            'folderProgress' => $folderProgress,
            'manageSections' => $manageSections,
            'topGroupProgress' => $topGroupProgress,
            'progressAnalysis' => $progressAnalysis,
            'attachmentRuns' => $attachmentRuns,
            'attachmentPdfHealth' => $attachmentPdfHealth,
            'attachmentsMinPercent' => $attachmentsMinPercent,
            'canGenerateAttachmentPackage' => $overallPercent >= $attachmentsMinPercent,
            'transferRequest' => $transferRequest,
            'canTransferToMga' => $canTransferToMga,
            'canRequestTransfer' => $canRequestTransfer,
            'canAuthorizeTransfer' => $canAuthorizeTransfer,
            'canAcknowledgeTransfer' => $canAcknowledgeTransfer,
            'transferReceiptStates' => $transferReceiptStates,
        ]);
    }


    public function storeCustomCertification(Request $request, Project $project, GoogleDriveService $drive)
    {
        $this->authorizeProjectMutation();

        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        $data = $request->validate([
            'nombre_certificacion' => ['required', 'string', 'max:180'],
            'archivo' => ['nullable', 'file', 'max:51200'],
        ], [
            'nombre_certificacion.required' => 'Escribe el nombre de la certificación.',
            'archivo.max' => 'El archivo debe pesar máximo 50MB.',
        ]);

        $name = $this->cleanCustomCertificationName((string) $data['nombre_certificacion']);
        if ($name === '') {
            return response()->json([
                'ok' => false,
                'message' => 'Escribe un nombre válido para la certificación.',
            ], 422);
        }

        if ($request->hasFile('archivo')) {
            if (!$project->drive_folder_id) {
                return response()->json([
                    'ok' => false,
                    'message' => 'El proyecto no tiene carpeta de Drive configurada para cargar el archivo.',
                ], 422);
            }

            if (!$drive->isAuthorized(auth()->id())) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Drive no está conectado para cargar el archivo.',
                ], 422);
            }
        }

        $normalizedName = $this->normalizeCustomCertificationName($name);
        $duplicate = $project->requisitos()
            ->where('requirements.carpeta', '3.3 Otras Certificaciones')
            ->where('requirements.visible', true)
            ->get(['requirements.id', 'requirements.nombre_documento', 'requirements.requisito'])
            ->first(function (Requirement $requirement) use ($normalizedName) {
                $candidate = (string) ($requirement->nombre_documento ?: $requirement->requisito);
                return $this->normalizeCustomCertificationName($candidate) === $normalizedName;
            });

        if ($duplicate) {
            return response()->json([
                'ok' => false,
                'message' => 'Ya existe una certificación libre con ese nombre en este proyecto.',
            ], 422);
        }

        $nextOrder = (string) ((int) Requirement::query()
            ->where('custom_project_id', $project->id)
            ->where('carpeta', '3.3 Otras Certificaciones')
            ->max('orden') + 1);

        $requirement = Requirement::query()->create([
            'codigo_norma' => null,
            'codigo_interno' => '3.3',
            'custom_project_id' => $project->id,
            'texto' => $name,
            'sector' => null,
            'tipo' => 'Certificación',
            'requiere_check' => 'SI',
            'orden' => $nextOrder,
            'literal' => null,
            'numeracion' => '3.3',
            'requisito' => $name,
            'nombre_documento' => $name,
            'carpeta' => '3.3 Otras Certificaciones',
            'origen' => 'custom_certification',
            'visible' => true,
        ]);

        $project->requisitos()->syncWithoutDetaching([$requirement->id]);

        $uploaded = null;
        if ($request->hasFile('archivo')) {
            $file = $request->file('archivo');
            $extension = $file?->getClientOriginalExtension();
            $targetName = $this->customCertificationFileName($name, $extension ?: null);
            try {
                $uploaded = $drive->uploadEvidence($project, $requirement, $file, $targetName, auth()->id());
            } catch (\Throwable $e) {
                return response()->json([
                    'ok' => true,
                    'message' => 'La certificación fue creada, pero no se pudo cargar el archivo inicial: ' . $e->getMessage(),
                    'requirement_id' => $requirement->id,
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'message' => $uploaded ? 'Certificación creada y archivo cargado.' : 'Certificación creada. Ahora puedes cargar su evidencia.',
            'requirement_id' => $requirement->id,
        ]);
    }

    public function upload(Request $request, Project $project, Requirement $requirement, GoogleDriveService $drive)
    {
        $this->authorizeProjectMutation();

        @ini_set('max_execution_time', '0');
        set_time_limit(0);

        $expectsJson = $request->expectsJson();
        $userId = auth()->id();
        $project->load('requisitos');

        if (!$project->requisitos->contains($requirement->id)) {
            return $this->uploadErrorResponse($expectsJson, 422, 'requirement_not_applicable', 'Este requisito no está marcado para el proyecto.');
        }

        /** @var RequirementProgressService $progressService */
        $progressService = app(RequirementProgressService::class);
        if ($progressService->isCompositeParent($requirement)) {
            $targetFolder = $progressService->compositeTargetFolder($requirement) ?: 'su carpeta hija';
            return $this->uploadErrorResponse(
                $expectsJson,
                422,
                'composite_requirement_upload_blocked',
                "Este requisito se cumple automáticamente con los documentos activos de la carpeta {$targetFolder}."
            );
        }

        $hadValidEvidenceBefore = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->where('requirement_id', $requirement->id)
            ->where('drive_folder_name', $requirement->carpeta)
            ->where('in_drive', true)
            ->exists();

        if (!$project->drive_folder_id) {
            return $this->uploadErrorResponse($expectsJson, 422, 'missing_drive_folder', 'El proyecto no tiene carpeta de Drive configurada.');
        }

        if (!$drive->isAuthorized($userId)) {
            return $this->uploadErrorResponse($expectsJson, 422, 'drive_not_authorized', 'Conecta Drive antes de cargar evidencias.');
        }

        $validator = Validator::make($request->all(), [
            'archivos' => ['required', 'array', 'min:1'],
            'archivos.*' => ['file', 'max:51200'],
        ], [
            'archivos.required' => 'Debes seleccionar al menos un archivo.',
            'archivos.*.max' => 'Cada archivo debe pesar máximo 50MB.',
        ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first() ?: 'Archivo inválido.';
            if ($expectsJson) {
                return response()->json([
                    'ok' => false,
                    'message' => $message,
                    'code' => 'validation_failed',
                    'errors' => $validator->errors(),
                ], 422);
            }
            return back()->withErrors($validator)->withInput();
        }

        $data = $validator->validated();
        $renumerated = $this->buildRenumerationMap($this->getActiveRequirementsForProject($project));
        $prefix = $renumerated[$requirement->id] ?? $requirement->codigo_interno ?? $requirement->numeracion ?? '';
        $baseName = $this->renumberBaseName($requirement);

        $uploadedCount = 0;
        $index = 1;
        $totalFiles = count($data['archivos']);
        foreach ($data['archivos'] as $archivo) {
            $suffix = $totalFiles > 1 ? " ({$index})" : '';
            $extension = $archivo->getClientOriginalExtension();
            $targetBase = $this->buildRenumberedFileBase($prefix, $baseName, $suffix);
            $targetName = $extension ? $targetBase . '.' . $extension : $targetBase;

            Log::info('manage_upload_attempt', [
                'project_id' => $project->id,
                'requirement_id' => $requirement->id,
                'user_id' => $userId,
                'filename' => $archivo->getClientOriginalName(),
                'target_name' => $targetName,
                'stage' => 'upload_to_drive',
            ]);

            try {
                $drive->uploadEvidence($project, $requirement, $archivo, $targetName, $userId);
                $uploadedCount++;
            } catch (\Throwable $e) {
                Log::error('manage_upload_failed', [
                    'project_id' => $project->id,
                    'requirement_id' => $requirement->id,
                    'user_id' => $userId,
                    'filename' => $archivo->getClientOriginalName(),
                    'target_name' => $targetName,
                    'stage' => 'upload_to_drive',
                    'error' => $e->getMessage(),
                ]);

                return $this->uploadErrorResponse(
                    $expectsJson,
                    500,
                    'drive_upload_failed',
                    $e->getMessage() ?: 'Error al subir a Drive.'
                );
            }
            $index++;
        }

        $requirementEvidences = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->where('requirement_id', $requirement->id)
            ->where('drive_folder_name', $requirement->carpeta)
            ->orderByDesc('id')
            ->get();

        $payload = [
            'id' => $requirement->id,
            'has_evidence' => $requirementEvidences->where('in_drive', true)->isNotEmpty(),
            'valid_evidence_count' => $requirementEvidences->where('in_drive', true)->count(),
            'evidences' => $requirementEvidences->map(function (RequirementEvidence $evidence) {
                return [
                    'id' => $evidence->id,
                    'name' => $evidence->drive_file_name,
                    'file_id' => $evidence->drive_file_id,
                    'source' => $evidence->source,
                    'is_valid' => (bool) $evidence->in_drive,
                ];
            })->values()->all(),
        ];

        if (!$hadValidEvidenceBefore && ($payload['has_evidence'] ?? false) && $userId) {
            event(new GamificationActivityTriggered('req_first_valid_evidence', (int) $userId, [
                'project_id' => (int) $project->id,
                'requirement_id' => (int) $requirement->id,
                'metadata' => ['uploaded_count' => (int) $uploadedCount],
            ]));
        }

        if ($expectsJson) {
            return response()->json([
                'ok' => true,
                'message' => 'Evidencias cargadas en Drive.',
                'uploaded_count' => $uploadedCount,
                'requirement' => $payload,
            ]);
        }

        return back()->with('status', 'Evidencias cargadas en Drive.');
    }


    private function cleanCustomCertificationName(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value));
        $value = trim((string) $value, " \t\n\r\0\x0B.-_");
        return $value;
    }

    private function normalizeCustomCertificationName(string $value): string
    {
        $value = Str::ascii($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function customCertificationFileName(string $name, ?string $extension): string
    {
        $base = str_replace(['ñ', 'Ñ'], ['n', 'N'], $name);
        $base = Str::ascii($base);
        $base = preg_replace('/[^A-Za-z0-9 _.-]+/', '', $base);
        $base = preg_replace('/\s+/', ' ', trim((string) $base));
        $base = trim((string) $base, ' ._-');
        if ($base === '') {
            $base = 'certificacion';
        }

        $extension = mb_strtolower(trim((string) $extension, '. '));
        return $extension !== '' ? $base . '.' . $extension : $base;
    }

    private function uploadErrorResponse(bool $expectsJson, int $status, string $code, string $message)
    {
        if ($expectsJson) {
            return response()->json([
                'ok' => false,
                'message' => $message,
                'code' => $code,
            ], $status);
        }

        return back()->with('error', $message);
    }

    public function renumberUploads(Request $request, Project $project, GoogleDriveService $drive)
    {
        $this->authorizeProjectMutation();

        $userId = auth()->id();
        if (!$project->drive_folder_id) {
            return back()->with('error', 'El proyecto no tiene carpeta de Drive configurada.');
        }
        if (!$drive->isAuthorized($userId)) {
            return back()->with('error', 'Conecta Drive antes de renumerar.');
        }

        $requirements = $this->getActiveRequirementsForProject($project);
        $renumerated = $this->buildRenumerationMap($requirements);

        $renamed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($requirements as $requirement) {
            $prefix = $renumerated[$requirement->id] ?? null;
            if (!$prefix) {
                continue;
            }

            $baseName = $this->renumberBaseName($requirement);
            if ($this->isEstudioRequirement($requirement)) {
                try {
                    $drive->renameRequirementFolderToPreferred($project, $requirement, $userId);
                } catch (\Throwable $e) {
                    Log::warning('study_folder_rename_failed', [
                        'project_id' => $project->id,
                        'requirement_id' => $requirement->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $evidences = RequirementEvidence::query()
                ->where('project_id', $project->id)
                ->where('requirement_id', $requirement->id)
                ->whereNotNull('drive_file_id')
                ->orderBy('id')
                ->get()
                ->filter(function (RequirementEvidence $evidence) use ($drive, $requirement) {
                    $isValid = $drive->validatesEvidence((string) ($evidence->drive_file_name ?? ''), $evidence->drive_mime_type, $requirement);
                    if ((bool) $evidence->in_drive !== $isValid) {
                        $evidence->forceFill(['in_drive' => $isValid])->save();
                    }
                    return $isValid;
                })
                ->values();

            $total = $evidences->count();
            $index = 1;
            foreach ($evidences as $evidence) {
                $currentName = (string) ($evidence->drive_file_name ?? '');
                $extension = pathinfo($currentName, PATHINFO_EXTENSION);
                $suffix = $total > 1 ? " ({$index})" : '';
                $targetBase = $this->buildRenumberedFileBase($prefix, $baseName, $suffix);
                $targetName = $extension !== '' ? $targetBase . '.' . $extension : $targetBase;

                if ($targetName === $currentName) {
                    $skipped++;
                    $index++;
                    continue;
                }

                try {
                    $updated = $drive->renameFile((string) $evidence->drive_file_id, $targetName, $userId);
                    $newName = $updated['name'] ?? $targetName;
                    $newMime = $updated['mimeType'] ?? $evidence->drive_mime_type;
                    $newModified = $updated['modifiedTime'] ?? $evidence->drive_modified_time;
                    if (is_string($newModified) && str_contains($newModified, 'T')) {
                        try {
                            $newModified = Carbon::parse($newModified)->format('Y-m-d H:i:s');
                        } catch (\Throwable $parseError) {
                            $newModified = $evidence->drive_modified_time;
                        }
                    }

                    // Keep DB consistent when the same Drive file_id is linked in multiple records/projects.
                    RequirementEvidence::query()
                        ->where('drive_file_id', (string) $evidence->drive_file_id)
                        ->update([
                            'drive_file_name' => $newName,
                            'drive_mime_type' => $newMime,
                            'drive_modified_time' => $newModified,
                        ]);
                    $renamed++;
                } catch (\Throwable $e) {
                    $failed++;
                    Log::error('renumber_upload_failed', [
                        'project_id' => $project->id,
                        'requirement_id' => $requirement->id,
                        'evidence_id' => $evidence->id,
                        'from' => $currentName,
                        'to' => $targetName,
                        'error' => $e->getMessage(),
                    ]);
                }

                $index++;
            }
        }

        return back()->with('status', "Renumeración completada. Renombrados: {$renamed}, sin cambio: {$skipped}, fallidos: {$failed}.");
    }

    private function buildRenumerationMap($requirements): array
    {
        $map = [];
        // Important: use the incoming collection order so numbering matches the same
        // order rendered in checklist/manage (no extra local re-sort here).
        $ordered = collect($requirements)->values();

        $counters = [];
        foreach ($ordered as $req) {
            if ($this->isEstudioRequirement($req)) {
                $studyKey = $this->studyRequirementGroupKey($req);
                $counters[$studyKey] = ($counters[$studyKey] ?? 0) + 1;
                $map[$req->id] = (string) $counters[$studyKey];
                continue;
            }

            $groupCode = $this->detectTopGroupCode((string) ($req->carpeta ?? '')) ?? '99';
            $major = ltrim($groupCode, '0');
            if ($major === '') {
                $major = $groupCode;
            }
            $folderKey = mb_strtolower(trim((string) ($req->carpeta ?? 'sin-carpeta')));
            $counterKey = $groupCode . '|' . $folderKey;
            $counters[$counterKey] = ($counters[$counterKey] ?? 0) + 1;
            $map[$req->id] = sprintf('%s.%02d', $major, $counters[$counterKey]);
        }

        return $map;
    }

    private function renumberBaseName(Requirement $requirement): string
    {
        $baseName = trim((string) ($requirement->nombre_documento ?: $requirement->requisito));
        if ($this->isEstudioRequirement($requirement)) {
            $baseName = preg_replace('/^\s*\d+(?:\.\d+)?[\s\-_]*/u', '', $baseName);
            $baseName = trim((string) $baseName);
        }

        return $baseName !== '' ? $baseName : 'Documento';
    }

    private function buildRenumberedFileBase(string $prefix, string $baseName, string $suffix = ''): string
    {
        $base = trim(implode(' ', array_filter([trim($prefix), trim($baseName)], fn ($part) => $part !== '')));
        return trim($base . $suffix);
    }

    private function studyRequirementGroupKey(Requirement $requirement): string
    {
        $code = trim((string) ($requirement->codigo_interno ?? $requirement->numeracion ?? ''));
        if (preg_match('/^\s*(5\.\d+)/', $code, $matches)) {
            return 'study|' . $matches[1];
        }

        return 'study|' . $this->normalizeFolderName((string) ($requirement->carpeta ?? 'sin-carpeta'));
    }

    private function isEstudioRequirement(Requirement $requirement): bool
    {
        $folder = $this->normalizeFolderName((string) ($requirement->carpeta ?? ''));
        $code = trim((string) ($requirement->codigo_interno ?? $requirement->numeracion ?? ''));

        if (str_contains($folder, 'estudios y disenos')) {
            return true;
        }

        return (bool) preg_match('/^\s*5(\.|$)/', $code);
    }

    private function normalizeFolderName(string $value): string
    {
        $value = Str::ascii($value);
        $value = Str::lower($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function getActiveRequirementsForProject(Project $project)
    {
        $requirements = $project->requisitos()
            ->where('requirements.visible', true)
            ->orderBy('carpeta')
            ->orderByRaw('custom_project_id IS NOT NULL')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->get();

        return $this->filterSectorial($requirements, $project);
    }

    private function filterSectorial($requirements, Project $project)
    {
        $sectorNames = $this->projectSectorCatalog($project)['names'];

        if (empty($sectorNames)) {
            return $requirements;
        }

        return $requirements->filter(function ($req) use ($sectorNames) {
            $carpeta = $this->normalizeSector($req->carpeta);
            if ($carpeta && str_contains($carpeta, 'documentos sectoriales')) {
                $reqSector = $this->normalizeSector($req->sector);
                if ($reqSector === '') {
                    return true;
                }
                return $this->sectorMatches($reqSector, $sectorNames);
            }
            return true;
        })->values();
    }

    private function sectorMatches(string $reqSector, array $projectSectors): bool
    {
        foreach ($projectSectors as $projectSector) {
            if ($reqSector === $projectSector) {
                return true;
            }

            $reqTokens = collect(explode(' ', str_replace(' y ', ' ', $reqSector)))
                ->filter(fn ($t) => $t !== '')
                ->values()
                ->all();
            $projTokens = collect(explode(' ', str_replace(' y ', ' ', $projectSector)))
                ->filter(fn ($t) => $t !== '')
                ->values()
                ->all();

            if (!empty($reqTokens) && !empty($projTokens)) {
                sort($reqTokens);
                sort($projTokens);
                if ($reqTokens === $projTokens) {
                    return true;
                }
            }

            if (str_contains($reqSector, $projectSector) || str_contains($projectSector, $reqSector)) {
                return true;
            }
        }

        return false;
    }

    private function projectSectorCatalog(Project $project): array
    {
        $primary = $project->sectores->first(fn ($sector) => (bool) ($sector->pivot->is_primary ?? false));
        if (!$primary) {
            $primary = $project->sectores->first();
        }
        $secondary = $project->sectores->filter(fn ($s) => !(bool) ($s->pivot->is_primary ?? false));
        if ($primary) {
            $secondary = $secondary->reject(fn ($s) => (int) $s->id === (int) $primary->id);
        }

        $ordered = collect();
        if ($primary) {
            $ordered->push([
                'name' => $primary->nombre,
                'normalized' => $this->normalizeSector($primary->nombre),
                'is_primary' => true,
            ]);
        }
        foreach ($secondary as $sector) {
            $ordered->push([
                'name' => $sector->nombre,
                'normalized' => $this->normalizeSector($sector->nombre),
                'is_primary' => false,
            ]);
        }

        $ordered = $ordered->filter(fn ($s) => $s['normalized'] !== '')->values();

        return [
            'ordered' => $ordered->all(),
            'names' => $ordered->pluck('normalized')->all(),
        ];
    }

    private function normalizeSector(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $value = \Illuminate\Support\Str::ascii($value);
        $value = mb_strtolower($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim($value);
    }

    private function buildManageSections($requirementsByFolder): array
    {
        $requirementsByFolder = collect($requirementsByFolder->all());
        $sections = [];
        foreach ($requirementsByFolder as $carpeta => $items) {
            $order = 999;
            if (preg_match('/^(\d+)/', (string) $carpeta, $m)) {
                $order = (int) $m[1];
            }
            $sections[] = [
                'type' => 'folder',
                'name' => $carpeta,
                'order' => $order,
                'items' => $items,
                'group_code' => str_pad((string) $order, 2, '0', STR_PAD_LEFT),
            ];
        }

        usort($sections, function ($a, $b) {
            if ($a['order'] === $b['order']) {
                return strcmp((string) $a['name'], (string) $b['name']);
            }
            return $a['order'] <=> $b['order'];
        });

        return $sections;
    }

    private function buildTopGroupProgress(array $folderProgress): array
    {
        $labels = [
            '01' => 'Formulacion',
            '02' => 'Presupuesto',
            '03' => 'Certificaciones',
            '04' => 'Licencias y Permisos',
            '05' => 'Estudios y Disenos',
        ];

        $summary = [];
        foreach ($labels as $code => $label) {
            $summary[$code] = [
                'code' => $code,
                'label' => $label,
                'total' => 0,
                'done' => 0,
                'percent' => 0,
            ];
        }

        foreach ($folderProgress as $folder => $progress) {
            $groupCode = $this->detectTopGroupCode((string) $folder);
            if (!$groupCode || !isset($summary[$groupCode])) {
                continue;
            }
            $summary[$groupCode]['total'] += (int) ($progress['total'] ?? 0);
            $summary[$groupCode]['done'] += (int) ($progress['done'] ?? 0);
        }

        foreach ($summary as $code => $item) {
            $percent = $item['total'] > 0
                ? (int) round(($item['done'] / $item['total']) * 100)
                : 0;
            $summary[$code]['percent'] = $percent;
        }

        return $summary;
    }

    private function detectTopGroupCode(string $folder): ?string
    {
        if (preg_match('/^\s*0?([1-5])(?:\D|$)/', $folder, $m)) {
            return str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        $normalized = strtolower(\Illuminate\Support\Str::ascii($folder));
        if (str_contains($normalized, 'formulacion')) {
            return '01';
        }
        if (str_contains($normalized, 'presupuesto')) {
            return '02';
        }
        if (str_contains($normalized, 'certificacion')) {
            return '03';
        }
        if (str_contains($normalized, 'licencias') || str_contains($normalized, 'permisos')) {
            return '04';
        }
        if (str_contains($normalized, 'estudios') || str_contains($normalized, 'disenos')) {
            return '05';
        }

        return null;
    }

    private function expireStaleAttachmentRuns(int $projectId): void
    {
        $threshold = now()->subMinutes(15);
        AttachmentPackageRun::query()
            ->where('project_id', $projectId)
            ->whereIn('status', ['pending', 'running'])
            ->where(function ($q) use ($threshold) {
                $q->where('updated_at', '<', $threshold)
                    ->orWhere(function ($inner) use ($threshold) {
                        $inner->whereNull('updated_at')
                            ->where('created_at', '<', $threshold);
                    });
            })
            ->update([
                'status' => 'failed',
                'error_message' => 'Proceso marcado como vencido por inactividad (15 min).',
                'finished_at' => now(),
            ]);
    }

    private function buildAttachmentPdfHealth(): array
    {
        return app(AttachmentPdfRuntime::class)->health();
    }

    private function authorizeProjectMutation(): void
    {
        $user = auth()->user();
        if (!$user || !$user->canMutateProjects()) {
            abort(403);
        }
    }
}
