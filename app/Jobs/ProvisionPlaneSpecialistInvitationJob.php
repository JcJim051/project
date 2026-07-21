<?php

namespace App\Jobs;

use App\Models\Specialist;
use App\Services\PlaneProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProvisionPlaneSpecialistInvitationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 180;
    public bool $failOnTimeout = true;
    public int $tries = 3;

    public function __construct(
        private readonly int $specialistId,
    ) {
        $this->onConnection('database');
    }

    public function handle(PlaneProvisioningService $service): void
    {
        $specialist = Specialist::query()->find($this->specialistId);
        if (! $specialist) {
            return;
        }

        $service->inviteSpecialistToWorkspace($specialist);
    }
}
