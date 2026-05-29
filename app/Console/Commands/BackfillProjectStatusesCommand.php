<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\ProjectStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillProjectStatusesCommand extends Command
{
    protected $signature = 'projects:backfill-statuses';

    protected $description = 'Recalcula estado de proyectos existentes según hitos actuales.';

    public function handle(ProjectStatusService $statusService): int
    {
        $updated = 0;
        $total = 0;
        $hasPointEventsTable = Schema::hasTable('user_point_events');

        Project::query()
            ->withCount('requisitos')
            ->withCount('transferRequests')
            ->chunkById(100, function ($projects) use ($statusService, &$updated, &$total, $hasPointEventsTable): void {
                foreach ($projects as $project) {
                    $total++;
                    $targetStatus = 'Iniciativa';

                    if ((int) $project->requisitos_count > 0) {
                        $targetStatus = 'Formulación y presentación';
                    }

                    if ((int) $project->transfer_requests_count > 0) {
                        $targetStatus = 'Viabilidad y registro';
                    }

                    if ($hasPointEventsTable) {
                        $transferred = DB::table('user_point_events')
                            ->where('activity_code', 'transferido_mga')
                            ->where('project_id', (int) $project->id)
                            ->exists();
                        if ($transferred) {
                            $targetStatus = 'Priorización y aprobación';
                        }
                    }

                    $before = (int) $project->project_status_id;
                    $statusService->setByName($project, $targetStatus);
                    if ((int) $project->project_status_id !== $before) {
                        $updated++;
                    }
                }
            });

        $this->info("Revisión completada. Proyectos revisados: {$total}. Estados actualizados: {$updated}.");

        return self::SUCCESS;
    }
}

