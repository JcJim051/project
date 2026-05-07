<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateAttachmentPackageJob;
use App\Models\AttachmentPackageRun;
use App\Models\Project;
use App\Models\RequirementEvidence;
use Illuminate\Http\Request;

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

    public function store(Project $project)
    {
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
        if ($percent < 100) {
            return back()->withErrors([
                'attachments_package' => 'La generación de adjuntos se habilita solo al 100%.',
            ]);
        }

        $run = AttachmentPackageRun::query()->create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'status' => 'pending',
            'progress_percent_snapshot' => $percent,
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
            'zip_available_local' => $run->zip_local_path && file_exists($run->zip_local_path),
            'generated_pdf_count' => $run->generated_pdf_count,
            'missing_count' => $run->missing_count,
            'stage_label' => data_get($run->meta, 'stage_label'),
            'stage_percent' => data_get($run->meta, 'stage_percent'),
            'stage_detail_percent' => data_get($run->meta, 'stage_detail_percent'),
            'heartbeat_at' => data_get($run->meta, 'heartbeat_at'),
            'error_message' => $run->error_message,
            'updated_at' => optional($run->updated_at)->toDateTimeString(),
        ]);
    }

    public function download(Project $project, AttachmentPackageRun $run)
    {
        abort_unless($run->project_id === $project->id, 404);
        abort_unless($run->zip_local_path && file_exists($run->zip_local_path), 404);

        return response()->download(
            $run->zip_local_path,
            $run->zip_filename ?: ('Adjuntos_V' . ($run->version_number ?? 1) . '.zip')
        );
    }

    private function overallPercent(Project $project): int
    {
        $project->loadMissing('requisitos');
        $requirements = $project->requisitos()->where('requirements.visible', true)->get();
        $total = $requirements->count();
        if ($total === 0) {
            return 0;
        }

        $done = 0;
        foreach ($requirements as $req) {
            $hasEvidence = RequirementEvidence::query()
                ->where('project_id', $project->id)
                ->where('requirement_id', $req->id)
                ->where('in_drive', true)
                ->exists();
            if ($hasEvidence) {
                $done++;
            }
        }

        return (int) round(($done / $total) * 100);
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
}
