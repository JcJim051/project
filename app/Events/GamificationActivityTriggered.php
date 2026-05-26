<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GamificationActivityTriggered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $activityCode,
        public readonly int $userId,
        public readonly array $context = []
    ) {
    }
}

