<?php

namespace App\Listeners;

use App\Events\GamificationActivityTriggered;
use App\Services\GamificationService;

class AwardGamificationPoints
{
    public function handle(GamificationActivityTriggered $event): void
    {
        app(GamificationService::class)->award(
            $event->activityCode,
            $event->userId,
            $event->context
        );
    }
}

