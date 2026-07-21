<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAttachmentPackageJob;
use App\Models\AttachmentPackageRun;
use App\Models\Project;
use App\Models\RequirementEvidence;
use App\Services\AttachmentPackageService;
use App\Services\GoogleDriveService;
use App\Services\MgaTransferAuthorizationService;
use App\Services\RequirementProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AttachmentPackageRunController extends Controller
{
    public function index(Project $project, Request $request)
    {
        $this->expireStaleRuns($project->id);

        $runs = AttachmentPackageRun::query()
            ->where('project_id', $project->id)
            ->latest('id')
            ->limit(50)
            ->get([
                'id',
                'status',
                'version_number',
                'zip_filename',
                'output_type',
                'output_filename',
                'generated_pdf_count',
                'missing_count',
                'error_message',
                'created_at',
                'updated_at',
            ]);

        if ($request->expectsJson()) {
            return response()->json([
                'project_id' => $project->id,
                'runs' => $runs,
            ]);
        }

        return redirect()
            ->route('projects.manage', $project)
            ->with('status', 'La generación de paquetes se gestiona desde esta vista.');
    }

    public function store(Project $project, Request $request)
    {
        $this->authorizeProjectMutation();
        $this->expireStaleRuns($project->id);

        $activeRun = AttachmentPackageRun::query()
            ->where('project_id', $project->id)
            ->whereIn('status', ['pending', 'running'])
            ->latest('id')
            ->first();
        if ($activeRun) {
            return back()->withErrors([
                'attachments_package' => 'Ya existe una generacion en curso (Run #' . $activeRun->id . '). Espera a que termine.',
            ]);
        }

        $percent = $this->overallPercent($project);
        $minPercent = $this->minRequiredPercent($project);
        if ($percent < $minPercent) {
            return back()->withErrors([
                'attachments_package' => "La generación de adjuntos se habilita a partir de {$minPercent}%.",
            ]);
        }

        /** @var MgaTransferAuthorizationService $mgaService */
        $mgaService = app(MgaTransferAuthorizationService::class);
        if (!$mgaService->isApprovalComplete($mgaService->current($project))) {
            $message = $mgaService->requiresPlanningApproval()
                ? 'La generación de carteras requiere aprobación interna de Dirección y Planeación AIM.'
                : 'La generación de carteras requiere aprobación interna de Dirección.';
            return back()->withErrors(['attachments_package' => $message]);
        }

        /** @var AttachmentPackageService $packageService */
        $packageService = app(AttachmentPackageService::class);
        $availableKeys = collect($packageService->availablePackageDocuments($project))->pluck('key')->all();
        $selected = collect($request->input('selected_documents', []))
            ->map(fn ($key) => (string) $key)
            ->filter(fn ($key) => in_array($key, $availableKeys, true))
            ->values()
            ->all();

        if (empty($selected)) {
            return back()->withErrors([
                'attachments_package' => 'Selecciona al menos una cartera para generar.',
            ]);
        }

        $versionMode = $request->input('version_mode') === 'current' ? 'current' : 'next';

        $project->forceFill(['attachment_package_selection' => $selected])->save();

        $run = AttachmentPackageRun::query()->create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'status' => 'pending',
            'progress_percent_snapshot' => $percent,
            'selected_documents' => $selected,
            'output_type' => count($selected) === 1 ? 'pdf' : 'zip',
            'meta' => [
                'version_mode' => $versionMode,
            ],
        ]);

        GenerateAttachmentPackageJob::dispatch($run->id)->onConnection('database');

        return back()->with('status', 'Generación de paquete en cola (worker).');
    }

    public function show(Project $project, AttachmentPackageRun $run)
    {
        abort_unless($run->project_id === $project->id, 404);
        $this->expireStaleRun($run);
        $run->refresh();

        return response()->json([
            'id' => $run->id,
            'status' => $run->status,
            'version_number' => $run->version_number,
            'zip_filename' => $run->zip_filename,
            'zip_available_local' => $this->hasAccessibleOutput($run),
            'output_type' => $run->output_type ?: 'zip',
            'output_filename' => $run->output_filename ?: $run->zip_filename,
            'generated_pdf_count' => $run->generated_pdf_count,
            'missing_count' => $run->missing_count,
            'stage_label' => data_get($run->meta, 'stage_label'),
            'stage_percent' => data_get($run->meta, 'stage_percent'),
            'stage_detail_percent' => data_get($run->meta, 'stage_detail_percent'),
            'heartbeat_at' => data_get($run->meta, 'heartbeat_at'),
            'drive_download_file_name' => data_get($run->meta, 'drive_download_file_name'),
            'drive_download_file_id' => data_get($run->meta, 'drive_download_file_id'),
            'drive_download_started_at' => data_get($run->meta, 'drive_download_started_at'),
            'error_message' => $run->error_message,
            'updated_at' => optional($run->updated_at)->toDateTimeString(),
        ]);
    }

    public function cancel(Project $project, AttachmentPackageRun $run)
    {
        $this->authorizeProjectMutation();
        abort_unless($run->project_id === $project->id, 404);

        if (in_array($run->status, ['pending', 'running'], true)) {
            $meta = is_array($run->meta) ? $run->meta : [];
            $meta['stage_label'] = 'Cancelado por el usuario';
            $meta['stage_percent'] = 100;
            $meta['stage_detail_percent'] = 100;
            $meta['heartbeat_at'] = now()->toDateTimeString();
            $meta['cancelled_at'] = now()->toDateTimeString();

            $run->forceFill([
                'status' => 'cancelled',
                'error_message' => 'Generación cancelada por el usuario.',
                'finished_at' => now(),
                'meta' => $meta,
            ])->save();

            DB::table('jobs')
                ->where('queue', 'default')
                ->where('payload', 'like', '%GenerateAttachmentPackageJob%')
                ->where('payload', 'like', '%runId%' . $run->id . '%')
                ->delete();
        }

        if (request()->expectsJson()) {
            return response()->json([
                'id' => $run->id,
                'status' => $run->fresh()->status,
            ]);
        }

        return back()->with('status', 'Generación cancelada.');
    }

    public function preview(Project $project, AttachmentPackageRun $run)
    {
        abort_unless($run->project_id === $project->id, 404);
        abort_unless(($run->output_type ?: 'zip') === 'pdf', 404);
        $path = $this->resolveRunAccessPath($run);
        abort_unless($path, 404);

        $filename = $run->output_filename ?: basename($path);

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($filename) . '"',
        ])->deleteFileAfterSend($this->shouldDeleteAfterSend($run, $path));
    }

    public function download(Project $project, AttachmentPackageRun $run)
    {
        abort_unless($run->project_id === $project->id, 404);
        $path = $this->resolveRunAccessPath($run);
        abort_unless($path, 404);

        return response()->download(
            $path,
            $run->output_filename ?: $run->zip_filename ?: ('Adjuntos_V' . ($run->version_number ?? 1) . '.zip')
        )->deleteFileAfterSend($this->shouldDeleteAfterSend($run, $path));
    }

    private function runLocalPath(AttachmentPackageRun $run): ?string
    {
        return $run->output_local_path ?: $run->zip_local_path;
    }

    private function hasAccessibleOutput(AttachmentPackageRun $run): bool
    {
        if ($run->drive_file_id) {
            return true;
        }

        $path = $this->runLocalPath($run);

        return $path && file_exists($path);
    }

    private function resolveRunAccessPath(AttachmentPackageRun $run): ?string
    {
        if ($run->drive_file_id) {
            return $this->downloadRunFromDrive($run);
        }

        $path = $this->runLocalPath($run);

        return $path && file_exists($path) ? $path : null;
    }

    private function downloadRunFromDrive(AttachmentPackageRun $run): ?string
    {
        if (!$run->drive_file_id) {
            return null;
        }

        $baseName = $run->output_filename ?: $run->zip_filename ?: ('adjuntos_v' . ($run->version_number ?? 1));
        $extension = pathinfo($baseName, PATHINFO_EXTENSION);
        $tempPath = tempnam(sys_get_temp_dir(), 'attachment_run_');
        if ($tempPath === false) {
            throw new \RuntimeException('No se pudo crear archivo temporal para la descarga.');
        }

        if ($extension !== '') {
            $renamed = $tempPath . '.' . Str::lower($extension);
            if (@rename($tempPath, $renamed)) {
                $tempPath = $renamed;
            }
        }

        try {
            app(GoogleDriveService::class)->downloadFile((string) $run->drive_file_id, $tempPath, auth()->id());
        } catch (\Throwable $e) {
            @unlink($tempPath);
            throw $e;
        }

        return $tempPath;
    }

    private function shouldDeleteAfterSend(AttachmentPackageRun $run, string $path): bool
    {
        $localPath = $this->runLocalPath($run);

        return !$localPath || realpath($localPath) !== realpath($path);
    }

    private function overallPercent(Project $project): int
    {
        $project->loadMissing('requisitos');
        $requirements = $project->requisitos()->where('requirements.visible', true)->get();
        $evidences = RequirementEvidence::query()->where('project_id', $project->id)->get();

        /** @var RequirementProgressService $progressService */
        $progressService = app(RequirementProgressService::class);
        $analysis = $progressService->analyze($requirements, $evidences);

        return $progressService->buildOverallProgress($requirements, $analysis)['percent'];
    }

    private function minRequiredPercent(Project $project): int
    {
        return max(1, min(100, (int) ($project->attachments_min_percent ?? 80)));
    }

    private function expireStaleRuns(int $projectId): void
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

    private function expireStaleRun(AttachmentPackageRun $run): void
    {
        if (!in_array($run->status, ['pending', 'running'], true)) {
            return;
        }

        $reference = $run->updated_at ?: $run->created_at;
        if (!$reference || $reference->gt(now()->subMinutes(15))) {
            return;
        }

        $run->update([
            'status' => 'failed',
            'error_message' => 'Proceso marcado como vencido por inactividad (15 min).',
            'finished_at' => now(),
        ]);
    }

    private function authorizeProjectMutation(): void
    {
        $user = auth()->user();
        if (!$user || !$user->canMutateProjects()) {
            abort(403);
        }
    }
}
