<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTransferRequest;
use App\Models\ProjectTransferRequestReceipt;
use App\Models\User;

class MgaTransferAuthorizationService
{
    public function current(Project $project): ?ProjectTransferRequest
    {
        return $project->transferRequests()
            ->with(['requestedBy', 'decidedBy', 'receipts.user'])
            ->latest('id')
            ->first();
    }

    public function hasPending(Project $project): bool
    {
        return $project->transferRequests()->where('status', 'pending')->exists();
    }

    public function canTransfer(Project $project, int $overallPercent): bool
    {
        $min = max(1, min(100, (int) ($project->attachments_min_percent ?? 80)));
        $current = $this->current($project);

        return $overallPercent >= $min && $current?->status === 'approved';
    }

    public function request(Project $project, User $user, ?string $note = null): ProjectTransferRequest
    {
        return ProjectTransferRequest::create([
            'project_id' => $project->id,
            'requested_by_user_id' => $user->id,
            'status' => 'pending',
            'request_note' => $note,
            'requested_at' => now(),
        ]);
    }

    public function decide(ProjectTransferRequest $request, User $user, string $status, string $note): ProjectTransferRequest
    {
        $request->update([
            'status' => $status,
            'decision_note' => $note,
            'decided_at' => now(),
            'decided_by_user_id' => $user->id,
        ]);

        return $request->fresh(['requestedBy', 'decidedBy', 'receipts.user']);
    }

    public function acknowledge(ProjectTransferRequest $request, User $user, ?string $note = null): ProjectTransferRequestReceipt
    {
        return ProjectTransferRequestReceipt::updateOrCreate(
            [
                'project_transfer_request_id' => $request->id,
                'user_id' => $user->id,
            ],
            [
                'acknowledged_at' => now(),
                'ack_note' => $note,
            ]
        );
    }
}

