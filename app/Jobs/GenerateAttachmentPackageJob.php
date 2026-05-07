<?php

namespace App\Jobs;

use App\Models\AttachmentPackageRun;
use App\Services\AttachmentPackageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAttachmentPackageJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;
    public bool $failOnTimeout = true;

    public function __construct(private readonly int $runId)
    {
    }

    public function handle(AttachmentPackageService $service): void
    {
        $run = AttachmentPackageRun::query()->find($this->runId);
        if (!$run) {
            return;
        }

        $run->update([
            'status' => 'running',
            'started_at' => now(),
            'error_message' => null,
            'meta' => array_merge(is_array($run->meta) ? $run->meta : [], [
                'stage_label' => 'Iniciando',
                'stage_percent' => 1,
                'heartbeat_at' => now()->toDateTimeString(),
            ]),
        ]);

        try {
            $service->generateAndUpload($run);
            $run->update([
                'status' => 'success',
                'finished_at' => now(),
                'meta' => array_merge(is_array($run->meta) ? $run->meta : [], [
                    'stage_label' => 'Finalizado',
                    'stage_percent' => 100,
                    'heartbeat_at' => now()->toDateTimeString(),
                ]),
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
                'meta' => array_merge(is_array($run->meta) ? $run->meta : [], [
                    'stage_label' => 'Fallo en la generacion',
                    'stage_percent' => 100,
                    'heartbeat_at' => now()->toDateTimeString(),
                ]),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $run = AttachmentPackageRun::query()->find($this->runId);
        if (!$run) {
            return;
        }

        if (in_array($run->status, ['success', 'failed'], true)) {
            return;
        }

        $run->update([
            'status' => 'failed',
            'error_message' => $e->getMessage() ?: 'La tarea fallo o excedio el tiempo limite.',
            'finished_at' => now(),
            'meta' => array_merge(is_array($run->meta) ? $run->meta : [], [
                'stage_label' => 'Fallo en la generacion',
                'stage_percent' => 100,
                'heartbeat_at' => now()->toDateTimeString(),
            ]),
        ]);
    }
}
