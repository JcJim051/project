<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementEvidence;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProjectDriveEvidenceController extends Controller
{
    public function listFiles(Project $project, Request $request, GoogleDriveService $drive)
    {
        $this->ensureDriveReady($project, $drive);

        $data = $request->validate([
            'requirement_id' => ['nullable', 'integer', 'exists:requirements,id'],
            'folder' => ['nullable', 'string', 'max:255'],
            'q' => ['nullable', 'string', 'max:255'],
            'ext' => ['nullable', 'string', 'max:10'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        if (empty($data['requirement_id']) && empty($data['folder'])) {
            return response()->json(['message' => 'Debes enviar requirement_id o folder.'], 422);
        }

        $requirement = !empty($data['requirement_id'])
            ? Requirement::query()->findOrFail((int) $data['requirement_id'])
            : $project->requisitos()
                ->where('requirements.carpeta', (string) $data['folder'])
                ->select('requirements.*')
                ->first();

        if (!$requirement) {
            return response()->json(['message' => 'No se encontró un requisito para la carpeta enviada.'], 404);
        }

        if (!$project->requisitos()->where('requirements.id', $requirement->id)->exists()) {
            abort(403, 'El requisito no pertenece al proyecto.');
        }

        $result = $drive->listRequirementFiles(
            $project,
            $requirement,
            auth()->id(),
            $data['q'] ?? null,
            $data['ext'] ?? null
        );

        $items = $result['items'];
        $page = (int) ($data['page'] ?? 1);
        $perPage = (int) ($data['per_page'] ?? 50);
        $total = (int) $items->count();
        $offset = ($page - 1) * $perPage;
        $slice = $items->slice($offset, $perPage)->values()->map(function (array $file) {
            $name = (string) ($file['name'] ?? '');
            return [
                'id' => $file['id'] ?? null,
                'name' => $name,
                'mime_type' => $file['mimeType'] ?? null,
                'modified_time' => $file['modifiedTime'] ?? null,
                'size' => $file['size'] ?? null,
                'ext' => strtolower(pathinfo($name, PATHINFO_EXTENSION)),
            ];
        })->all();

        return response()->json([
            'folder_label' => $result['folder_label'] ?? null,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'data' => $slice,
        ]);
    }

    public function linkFile(Project $project, Requirement $requirement, Request $request, GoogleDriveService $drive)
    {
        $this->authorizeProjectMutation();
        $this->ensureDriveReady($project, $drive);
        $this->ensureRequirementInProject($project, $requirement);

        $data = $request->validate([
            'file_ids' => ['required', 'array', 'min:1'],
            'file_ids.*' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $linked = [];
        $conflicts = [];
        foreach ($data['file_ids'] as $fileId) {
            $existing = RequirementEvidence::query()
                ->where('project_id', $project->id)
                ->where('drive_file_id', $fileId)
                ->first();

            if ($existing && (int) $existing->requirement_id !== (int) $requirement->id) {
                $conflicts[] = [
                    'file_id' => $fileId,
                    'current_requirement_id' => (int) $existing->requirement_id,
                ];
                continue;
            }

            $meta = $drive->getDriveFileMeta($fileId, auth()->id());
            $row = $drive->linkRequirementToDriveFile($project, $requirement, $meta, auth()->id(), $data['note'] ?? null);
            $linked[] = $row->id;
        }

        return response()->json([
            'linked_count' => count($linked),
            'conflicts_count' => count($conflicts),
            'conflicts' => $conflicts,
        ]);
    }


    public function unlinkFile(Project $project, Requirement $requirement, RequirementEvidence $evidence)
    {
        $this->authorizeProjectMutation();
        $this->ensureRequirementInProject($project, $requirement);

        if ((int) $evidence->project_id !== (int) $project->id || (int) $evidence->requirement_id !== (int) $requirement->id) {
            abort(404);
        }

        $evidence->delete();

        return response()->json([
            'ok' => true,
        ]);
    }

    public function deleteDriveFile(Project $project, Requirement $requirement, RequirementEvidence $evidence, GoogleDriveService $drive)
    {
        $this->authorizeProjectMutation();
        $this->ensureDriveReady($project, $drive);
        $this->ensureRequirementInProject($project, $requirement);

        if ((int) $evidence->project_id !== (int) $project->id || (int) $evidence->requirement_id !== (int) $requirement->id) {
            abort(404);
        }

        if (!$evidence->drive_file_id) {
            return response()->json([
                'ok' => false,
                'message' => 'La evidencia no tiene file_id de Drive.',
            ], 422);
        }

        $fileId = (string) $evidence->drive_file_id;
        $drive->deleteFile($fileId, auth()->id());

        // Keep history rows but mark file as unavailable after physical deletion in Drive.
        RequirementEvidence::query()
            ->where('drive_file_id', $fileId)
            ->update([
                'in_drive' => false,
            ]);

        return response()->json([
            'ok' => true,
            'message' => 'Archivo eliminado de Drive.',
        ]);
    }
    public function linkFilesBulk(Project $project, Request $request, GoogleDriveService $drive)
    {
        $this->authorizeProjectMutation();
        $this->ensureDriveReady($project, $drive);

        $data = $request->validate([
            'links' => ['required', 'array', 'min:1'],
            'links.*.requirement_id' => ['required', 'integer', 'exists:requirements,id'],
            'links.*.file_id' => ['required', 'string'],
            'links.*.note' => ['nullable', 'string', 'max:1000'],
        ]);

        $rows = collect($data['links'])->values();
        $report = [
            'linked' => [],
            'omitted' => [],
            'conflicts' => [],
        ];

        DB::transaction(function () use ($rows, $project, $drive, &$report): void {
            foreach ($rows as $row) {
                $requirement = Requirement::query()->find((int) $row['requirement_id']);
                if (!$requirement || !$project->requisitos()->where('requirements.id', $requirement->id)->exists()) {
                    $report['omitted'][] = [
                        'requirement_id' => (int) ($row['requirement_id'] ?? 0),
                        'file_id' => (string) ($row['file_id'] ?? ''),
                        'reason' => 'Requisito no pertenece al proyecto.',
                    ];
                    continue;
                }

                $existing = RequirementEvidence::query()
                    ->where('project_id', $project->id)
                    ->where('drive_file_id', (string) $row['file_id'])
                    ->first();
                if ($existing && (int) $existing->requirement_id !== (int) $requirement->id) {
                    $report['conflicts'][] = [
                        'requirement_id' => $requirement->id,
                        'file_id' => (string) $row['file_id'],
                        'current_requirement_id' => (int) $existing->requirement_id,
                    ];
                    continue;
                }

                try {
                    $meta = $drive->getDriveFileMeta((string) $row['file_id'], auth()->id());
                    $evidence = $drive->linkRequirementToDriveFile(
                        $project,
                        $requirement,
                        $meta,
                        auth()->id(),
                        $row['note'] ?? null
                    );
                    $report['linked'][] = [
                        'requirement_id' => $requirement->id,
                        'file_id' => (string) $row['file_id'],
                        'evidence_id' => $evidence->id,
                    ];
                } catch (\Throwable $e) {
                    $report['omitted'][] = [
                        'requirement_id' => $requirement->id,
                        'file_id' => (string) $row['file_id'],
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        });

        return response()->json([
            'linked_count' => count($report['linked']),
            'omitted_count' => count($report['omitted']),
            'conflicts_count' => count($report['conflicts']),
            'report' => $report,
        ]);
    }

    private function ensureRequirementInProject(Project $project, Requirement $requirement): void
    {
        if (!$project->requisitos()->where('requirements.id', $requirement->id)->exists()) {
            abort(403, 'El requisito no pertenece al proyecto.');
        }
    }

    private function ensureDriveReady(Project $project, GoogleDriveService $drive): void
    {
        if (!$project->drive_folder_id) {
            abort(422, 'El proyecto no tiene carpeta de Drive configurada.');
        }
        if (!$drive->isAuthorized(auth()->id())) {
            abort(422, 'Conecta Drive antes de vincular evidencias.');
        }
    }

    private function authorizeProjectMutation(): void
    {
        $user = auth()->user();
        if (!$user || !$user->canMutateProjects()) {
            abort(403);
        }
    }
}
