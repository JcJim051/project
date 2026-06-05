<?php

namespace App\Http\Controllers;

use App\Models\DriveUploadSession;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementEvidence;
use App\Models\User;
use App\Services\GoogleDriveService;
use App\Services\RequirementProgressService;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DriveUploadSessionController extends Controller
{
    private const DEFAULT_CHUNK_SIZE = 8 * 1024 * 1024;
    private const DEFAULT_MAX_CONCURRENT = 1;

    public function init(Project $project, Requirement $requirement, Request $request, GoogleDriveService $drive)
    {
        $userId = (int) auth()->id();
        $this->assertRequirementIsUploadable($project, $requirement, $drive, $userId);

        $data = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'mime_type' => ['nullable', 'string', 'max:255'],
            'index' => ['nullable', 'integer', 'min:1'],
            'total' => ['nullable', 'integer', 'min:1'],
        ])->validate();

        $targetName = $this->targetNameForUpload($project, $requirement, (string) $data['name'], (int) ($data['index'] ?? 1), (int) ($data['total'] ?? 1));
        $mimeType = (string) ($data['mime_type'] ?: 'application/octet-stream');

        $session = DriveUploadSession::query()
            ->where('project_id', $project->id)
            ->where('requirement_id', $requirement->id)
            ->where('user_id', $userId)
            ->where('original_name', (string) $data['name'])
            ->where('target_name', $targetName)
            ->where('size_bytes', (int) $data['size'])
            ->whereIn('status', ['pending', 'uploading', 'failed'])
            ->where('updated_at', '>=', now()->subHours(24))
            ->whereNull('drive_file_id')
            ->latest('id')
            ->first();

        if ($session && $session->resumable_url) {
            $session->forceFill([
                'status' => 'uploading',
                'mime_type' => $mimeType,
                'error_message' => null,
                'failed_at' => null,
                'started_at' => $session->started_at ?: now(),
            ])->save();

            return response()->json([
                'ok' => true,
                'session' => $this->sessionPayload($session->fresh(), true),
                'chunk_size' => self::DEFAULT_CHUNK_SIZE,
                'max_concurrent' => self::DEFAULT_MAX_CONCURRENT,
                'resumed' => true,
            ]);
        }

        $sessionInfo = $drive->createResumableUploadSession(
            $project,
            $requirement,
            $targetName,
            $mimeType,
            (int) $data['size'],
            $userId
        );

        $session = DriveUploadSession::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'user_id' => $userId,
            'status' => 'uploading',
            'original_name' => (string) $data['name'],
            'target_name' => $targetName,
            'mime_type' => $mimeType,
            'size_bytes' => (int) $data['size'],
            'uploaded_bytes' => 0,
            'drive_folder_id' => $sessionInfo['folder_id'] ?? null,
            'resumable_url' => $sessionInfo['upload_url'] ?? null,
            'started_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'session' => $this->sessionPayload($session, true),
            'chunk_size' => self::DEFAULT_CHUNK_SIZE,
            'max_concurrent' => self::DEFAULT_MAX_CONCURRENT,
            'resumed' => false,
        ]);
    }

    public function progress(DriveUploadSession $session, Request $request)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'uploaded_bytes' => ['required', 'integer', 'min:0'],
        ]);

        $session->forceFill([
            'uploaded_bytes' => min((int) $data['uploaded_bytes'], (int) $session->size_bytes),
            'status' => $session->status === 'pending' ? 'uploading' : $session->status,
        ])->save();

        return response()->json([
            'ok' => true,
            'session' => $this->sessionPayload($session->fresh()),
        ]);
    }

    public function complete(DriveUploadSession $session, Request $request, GoogleDriveService $drive)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'drive_file_id' => ['required', 'string', 'max:255'],
        ]);

        $session->load(['project', 'requirement']);
        $evidence = $drive->createUploadedEvidenceFromDriveFile(
            $session->project,
            $session->requirement,
            (string) $data['drive_file_id'],
            (int) $session->user_id,
            'Carga directa resumible a Drive'
        );

        $session->forceFill([
            'status' => 'completed',
            'drive_file_id' => (string) $data['drive_file_id'],
            'uploaded_bytes' => (int) $session->size_bytes,
            'completed_at' => now(),
            'error_message' => null,
        ])->save();

        $this->notifyUpload($session->fresh(['project', 'requirement']), true);

        return response()->json([
            'ok' => true,
            'message' => 'Carga completada en Drive.',
            'session' => $this->sessionPayload($session->fresh()),
            'requirement' => $this->requirementPayload($session->project, $session->requirement),
            'evidence_id' => $evidence->id,
        ]);
    }

    public function fail(DriveUploadSession $session, Request $request)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'message' => ['nullable', 'string', 'max:2000'],
            'drive_file_id' => ['nullable', 'string', 'max:255'],
        ]);

        $session->forceFill([
            'status' => 'failed',
            'drive_file_id' => $data['drive_file_id'] ?? $session->drive_file_id,
            'error_message' => (string) ($data['message'] ?? 'La carga falló.'),
            'failed_at' => now(),
        ])->save();

        $this->notifyUpload($session->fresh(['project', 'requirement']), false);

        return response()->json([
            'ok' => true,
            'session' => $this->sessionPayload($session->fresh()),
        ]);
    }

    public function cancel(DriveUploadSession $session)
    {
        $this->authorizeSession($session);

        $session->forceFill([
            'status' => 'cancelled',
            'error_message' => 'Carga cancelada por el usuario.',
            'failed_at' => now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'session' => $this->sessionPayload($session->fresh()),
        ]);
    }

    public function verify(DriveUploadSession $session, Request $request, GoogleDriveService $drive)
    {
        $this->authorizeSession($session);

        $data = $request->validate([
            'drive_file_id' => ['nullable', 'string', 'max:255'],
        ]);

        $driveFileId = (string) ($data['drive_file_id'] ?: $session->drive_file_id);
        if ($driveFileId === '' && $session->drive_folder_id && $session->target_name) {
            $found = $drive->findDirectFileInFolderByName((string) $session->drive_folder_id, (string) $session->target_name, (int) $session->user_id, (int) $session->size_bytes);
            $driveFileId = (string) ($found['id'] ?? '');
        }

        if ($driveFileId === '') {
            return response()->json([
                'ok' => false,
                'message' => 'No se encontró aún el archivo final en Drive. Intenta reintentar la carga.',
            ], 422);
        }

        $session->load(['project', 'requirement']);
        $drive->createUploadedEvidenceFromDriveFile($session->project, $session->requirement, $driveFileId, (int) $session->user_id, 'Carga verificada manualmente');
        $session->forceFill([
            'status' => 'completed',
            'drive_file_id' => $driveFileId,
            'uploaded_bytes' => (int) $session->size_bytes,
            'completed_at' => now(),
            'error_message' => null,
        ])->save();

        $this->notifyUpload($session->fresh(['project', 'requirement']), true, 'Carga verificada y vinculada');

        return response()->json([
            'ok' => true,
            'message' => 'Carga verificada y vinculada.',
            'session' => $this->sessionPayload($session->fresh()),
            'requirement' => $this->requirementPayload($session->project, $session->requirement),
        ]);
    }

    private function assertRequirementIsUploadable(Project $project, Requirement $requirement, GoogleDriveService $drive, int $userId): void
    {
        $project->load('requisitos');
        if (!$project->requisitos->contains($requirement->id)) {
            abort(response()->json(['ok' => false, 'message' => 'Este requisito no está marcado para el proyecto.'], 422));
        }

        /** @var RequirementProgressService $progressService */
        $progressService = app(RequirementProgressService::class);
        if ($progressService->isCompositeParent($requirement)) {
            $targetFolder = $progressService->compositeTargetFolder($requirement) ?: 'su subgrupo';
            abort(response()->json(['ok' => false, 'message' => "Este requisito se cumple automáticamente con los documentos activos de la carpeta {$targetFolder}."], 422));
        }

        if (!$project->drive_folder_id) {
            abort(response()->json(['ok' => false, 'message' => 'El proyecto no tiene carpeta de Drive configurada.'], 422));
        }
        if (!$drive->isAuthorized($userId)) {
            abort(response()->json(['ok' => false, 'message' => 'Drive no está conectado.'], 422));
        }
    }

    private function authorizeSession(DriveUploadSession $session): void
    {
        if ((int) $session->user_id !== (int) auth()->id() && !auth()->user()?->isAdminUser()) {
            abort(403);
        }
    }

    private function targetNameForUpload(Project $project, Requirement $requirement, string $originalName, int $index = 1, int $total = 1): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $prefix = $this->renumerationMap($project)[$requirement->id] ?? $requirement->codigo_interno ?? $requirement->numeracion ?? '';
        $baseName = $this->renumberBaseName($requirement);
        $suffix = $total > 1 ? " ({$index})" : '';
        $targetBase = trim(implode(' ', array_filter([trim((string) $prefix), trim($baseName)], fn ($part) => $part !== '')) . $suffix);

        return $extension ? $targetBase . '.' . $extension : $targetBase;
    }

    private function renumerationMap(Project $project): array
    {
        $requirements = $project->requisitos()
            ->where('requirements.visible', true)
            ->orderBy('carpeta')
            ->orderByRaw('custom_project_id IS NOT NULL')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->get();

        $map = [];
        $counters = [];
        foreach ($requirements as $req) {
            if ($this->isEstudioRequirement($req)) {
                $studyKey = $this->studyRequirementGroupKey($req);
                $counters[$studyKey] = ($counters[$studyKey] ?? 0) + 1;
                $map[$req->id] = (string) $counters[$studyKey];
                continue;
            }

            $groupCode = $this->detectTopGroupCode((string) ($req->carpeta ?? '')) ?? '99';
            $major = ltrim($groupCode, '0') ?: $groupCode;
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

        return str_contains($folder, 'estudios y disenos') || (bool) preg_match('/^\s*5(\.|$)/', $code);
    }

    private function normalizeFolderName(string $value): string
    {
        $value = Str::ascii($value);
        $value = Str::lower($value);
        $value = preg_replace('/\s+/', ' ', $value);
        return trim((string) $value);
    }

    private function detectTopGroupCode(string $folder): ?string
    {
        if (preg_match('/^(\d+)/', trim($folder), $m)) {
            return str_pad((string) ((int) $m[1]), 2, '0', STR_PAD_LEFT);
        }
        return null;
    }

    private function requirementPayload(Project $project, Requirement $requirement): array
    {
        $rows = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->where('requirement_id', $requirement->id)
            ->where('drive_folder_name', $requirement->carpeta)
            ->latest('id')
            ->get();

        $visible = $rows->filter(fn ($evidence) => (bool) $evidence->in_drive)->values();

        return [
            'id' => $requirement->id,
            'has_evidence' => $visible->isNotEmpty(),
            'valid_evidence_count' => $visible->count(),
            'evidences' => $visible->map(fn ($evidence) => [
                'id' => $evidence->id,
                'name' => $evidence->drive_file_name,
                'file_id' => $evidence->drive_file_id,
                'source' => $evidence->source,
                'is_valid' => (bool) $evidence->in_drive,
                'unlink_url' => route('projects.requirements.unlink_drive_file', [$project, $requirement, $evidence]),
                'delete_drive_url' => route('projects.requirements.delete_drive_file', [$project, $requirement, $evidence]),
            ])->all(),
            'history' => $rows->map(fn ($evidence) => [
                'id' => $evidence->id,
                'name' => $evidence->drive_file_name,
                'file_id' => $evidence->drive_file_id,
                'source' => $evidence->source,
                'is_valid' => (bool) $evidence->in_drive,
                'created_at' => optional($evidence->created_at)->format('Y-m-d H:i'),
            ])->all(),
        ];
    }

    private function sessionPayload(DriveUploadSession $session, bool $includeUploadUrl = false): array
    {
        $payload = [
            'id' => $session->id,
            'status' => $session->status,
            'original_name' => $session->original_name,
            'target_name' => $session->target_name,
            'mime_type' => $session->mime_type,
            'size_bytes' => (int) $session->size_bytes,
            'uploaded_bytes' => (int) $session->uploaded_bytes,
            'drive_file_id' => $session->drive_file_id,
            'error_message' => $session->error_message,
            'progress_url' => route('drive-upload-sessions.progress', $session),
            'complete_url' => route('drive-upload-sessions.complete', $session),
            'fail_url' => route('drive-upload-sessions.fail', $session),
            'cancel_url' => route('drive-upload-sessions.cancel', $session),
            'verify_url' => route('drive-upload-sessions.verify', $session),
        ];

        if ($includeUploadUrl) {
            $payload['upload_url'] = $session->resumable_url;
        }

        return $payload;
    }

    private function notifyUpload(DriveUploadSession $session, bool $success, ?string $title = null): void
    {
        $user = User::query()->find((int) $session->user_id);
        if (!$user) {
            return;
        }

        $projectName = (string) ($session->project?->nombre_clave ?: $session->project?->nombre ?: ('Proyecto #' . $session->project_id));
        $notification = FilamentNotification::make()
            ->title($title ?: ($success ? 'Carga completada' : 'Carga fallida'))
            ->body($success
                ? "{$projectName}: {$session->target_name} quedó cargado en Drive."
                : "{$projectName}: no se pudo cargar {$session->original_name}. " . ((string) $session->error_message ?: 'Revisa la cola de carga.'))
            ->icon($success ? 'heroicon-o-check-circle' : 'heroicon-o-x-circle')
            ->iconColor($success ? 'success' : 'danger')
            ->actions([
                NotificationAction::make('open')
                    ->label('Ver proyecto')
                    ->url(route('filament.admin.resources.projects.manage', ['record' => $session->project_id]), shouldOpenInNewTab: false),
            ]);

        $user->notifyNow($notification->toDatabase());
        DatabaseNotificationsSent::dispatch($user);
    }
}
