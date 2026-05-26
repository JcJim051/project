<?php

namespace App\Observers;

use App\Models\PointActivity;
use App\Models\PointActivityAudit;

class PointActivityObserver
{
    public function created(PointActivity $activity): void
    {
        $this->log($activity, 'created', null, $activity->toArray());
    }

    public function updated(PointActivity $activity): void
    {
        $this->log($activity, 'updated', $activity->getOriginal(), $activity->toArray());
    }

    public function deleted(PointActivity $activity): void
    {
        $this->log($activity, 'deleted', $activity->toArray(), null);
    }

    private function log(PointActivity $activity, string $action, ?array $before, ?array $after): void
    {
        PointActivityAudit::query()->create([
            'point_activity_id' => $activity->id,
            'changed_by' => auth()->id(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }
}

