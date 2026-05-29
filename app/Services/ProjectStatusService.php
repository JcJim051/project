<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectStatus;

class ProjectStatusService
{
    public function setByName(Project $project, string $name): void
    {
        $statusId = ProjectStatus::query()
            ->where('activo', true)
            ->where('nombre', $name)
            ->value('id');

        if (!$statusId) {
            return;
        }

        if ((int) $project->project_status_id !== (int) $statusId) {
            $project->forceFill(['project_status_id' => (int) $statusId])->save();
        }
    }
}

