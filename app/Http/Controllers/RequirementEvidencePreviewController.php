<?php

namespace App\Http\Controllers;

use App\Models\RequirementEvidence;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequirementEvidencePreviewController extends Controller
{
    public function show(Request $request, RequirementEvidence $evidence, GoogleDriveService $drive)
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $project = $evidence->project;
        abort_unless($project, 404);
        abort_unless($this->canAccessEvidence($user, $project), 403);

        if (!$evidence->drive_file_id) {
            abort(404, 'La evidencia no tiene archivo asociado en Drive.');
        }

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

        return response()->file($tmpPath, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . $downloadName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
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
