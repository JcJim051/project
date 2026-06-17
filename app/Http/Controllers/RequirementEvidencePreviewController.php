<?php

namespace App\Http\Controllers;

use App\Models\RequirementEvidence;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class RequirementEvidencePreviewController extends Controller
{
    public function show(Request $request, RequirementEvidence $evidence, GoogleDriveService $drive)
    {
        [$user, $project] = $this->authorizeAccess($request, $evidence);

        if (!$evidence->canPreviewInPortal()) {
            return $this->previewUnavailableResponse($evidence, $project);
        }

        try {
            return $this->streamEvidence($drive, $evidence, $user, false);
        } catch (\Throwable $e) {
            return $this->fileUnavailableResponse(
                'Archivo no disponible',
                'El archivo ya no está disponible en Drive.',
                404
            );
        }
    }

    public function download(Request $request, RequirementEvidence $evidence, GoogleDriveService $drive)
    {
        [$user] = $this->authorizeAccess($request, $evidence);

        try {
            return $this->streamEvidence($drive, $evidence, $user, true);
        } catch (\Throwable $e) {
            return $this->fileUnavailableResponse(
                'Archivo no disponible',
                'El archivo ya no está disponible en Drive.',
                404
            );
        }
    }

    private function authorizeAccess(Request $request, RequirementEvidence $evidence): array
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $project = $evidence->project;
        abort_unless($project, 404);
        abort_unless($this->canAccessEvidence($user, $project), 403);

        if (!$evidence->drive_file_id) {
            abort(404, 'La evidencia no tiene archivo asociado en Drive.');
        }

        return [$user, $project];
    }

    private function streamEvidence(GoogleDriveService $drive, RequirementEvidence $evidence, User $user, bool $download)
    {
        $meta = $drive->getDriveFileMeta((string) $evidence->drive_file_id, $user->id);
        $mimeType = (string) ($meta['mimeType'] ?: $evidence->drive_mime_type ?: 'application/octet-stream');

        $extension = pathinfo((string) ($meta['name'] ?: $evidence->drive_file_name ?: 'archivo'), PATHINFO_EXTENSION);
        $tmpPath = tempnam(sys_get_temp_dir(), 'evidence_preview_');
        if ($extension !== '') {
            $renamed = $tmpPath . '.' . $extension;
            @rename($tmpPath, $renamed);
            $tmpPath = $renamed;
        }

        $drive->downloadFile((string) $evidence->drive_file_id, $tmpPath, $user->id);

        $downloadName = $this->safeDownloadName((string) ($meta['name'] ?: $evidence->drive_file_name ?: 'evidencia'));

        if ($download) {
            return response()->download($tmpPath, $downloadName, [
                'Content-Type' => $mimeType,
                'X-Content-Type-Options' => 'nosniff',
            ])->deleteFileAfterSend(true);
        }

        return response()->file($tmpPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    private function previewUnavailableResponse(RequirementEvidence $evidence, $project): View
    {
        return view('requirement-evidences.preview-unavailable', [
            'title' => 'Vista previa no disponible',
            'message' => 'Este archivo no se puede previsualizar aquí. Descárgalo para abrirlo.',
            'fileName' => $evidence->drive_file_name ?: 'Archivo',
            'projectName' => $project->nombre ?: 'Proyecto',
            'downloadUrl' => route('requirement-evidences.download', ['evidence' => $evidence]),
        ]);
    }

    private function fileUnavailableResponse(string $title, string $message, int $status): Response
    {
        return response()->view('requirement-evidences.preview-unavailable', [
            'title' => $title,
            'message' => $message,
            'fileName' => null,
            'projectName' => null,
            'downloadUrl' => null,
        ], $status);
    }

    private function canAccessEvidence(User $user, $project): bool
    {
        if ($user->isAdminUser() || $user->hasAnyRole(['director', 'formulador_maestro'])) {
            return true;
        }

        if ($user->canAuthorizeMgaTransfer()) {
            return true;
        }

        return (int) $project->formulador_id === (int) $user->id
            || (int) $project->estructurador_id === (int) $user->id;
    }

    private function safeDownloadName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'evidencia';
        }

        $ascii = Str::ascii($name);
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $ascii);

        return trim((string) $ascii, '_') ?: 'evidencia';
    }
}
