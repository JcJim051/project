<?php

namespace App\Listeners;

use App\Events\GamificationActivityTriggered;
use App\Models\Project;
use App\Services\GamificationService;
use App\Services\ProjectStatusService;

class AwardGamificationPoints
{
    public function handle(GamificationActivityTriggered $event): void
    {
        if ($event->activityCode === 'transferido_mga') {
            $projectId = (int) ($event->context['project_id'] ?? 0);
            if ($projectId > 0) {
                $project = Project::query()->find($projectId);
                if ($project) {
                    app(ProjectStatusService::class)->setByName($project, 'Priorización y aprobación');
                }
            }
        }

        app(GamificationService::class)->award(
            $event->activityCode,
            $event->userId,
            $event->context
        );
    }
}
