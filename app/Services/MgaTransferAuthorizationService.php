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
            ->with(['requestedBy', 'decidedBy', 'directorDecidedBy', 'planningDecidedBy', 'receipts.user'])
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

        return $overallPercent >= $min && $this->isApprovalComplete($current);
    }

    public function isApprovalComplete(?ProjectTransferRequest $request): bool
    {
        if (!$request) {
            return false;
        }

        return $request->approvalComplete($this->requiresPlanningApproval());
    }

    public function requiresPlanningApproval(): bool
    {
        return app(ProcessSettingsService::class)->requirePlanningAimApproval();
    }

    public function request(Project $project, User $user, ?string $note = null): ProjectTransferRequest
    {
        return ProjectTransferRequest::create([
            'project_id' => $project->id,
            'requested_by_user_id' => $user->id,
            'status' => 'pending',
            'director_status' => 'pending',
            'planning_status' => 'pending',
            'request_note' => $note,
            'requested_at' => now(),
        ]);
    }

    public function decideDirection(ProjectTransferRequest $request, User $user, string $decision, string $note): ProjectTransferRequest
    {
        $status = $decision === 'approve' ? 'approved' : 'rejected';
        $request->forceFill([
            'director_status' => $status,
            'director_note' => $note,
            'director_decided_at' => now(),
            'director_decided_by_user_id' => $user->id,
        ]);

        return $this->syncOverallStatus($request, $user);
    }

    public function decidePlanning(ProjectTransferRequest $request, User $user, string $decision, string $note): ProjectTransferRequest
    {
        $status = $decision === 'approve' ? 'approved' : 'rejected';
        $request->forceFill([
            'planning_status' => $status,
            'planning_note' => $note,
            'planning_decided_at' => now(),
            'planning_decided_by_user_id' => $user->id,
        ]);

        return $this->syncOverallStatus($request, $user);
    }

    public function decide(ProjectTransferRequest $request, User $user, string $status, string $note): ProjectTransferRequest
    {
        return $this->decideDirection($request, $user, $status === 'approved' ? 'approve' : 'reject', $note);
    }

    public function syncOverallStatus(ProjectTransferRequest $request, ?User $decider = null): ProjectTransferRequest
    {
        $requiresPlanning = $this->requiresPlanningApproval();
        $directorStatus = (string) ($request->director_status ?: 'pending');
        $planningStatus = (string) ($request->planning_status ?: 'pending');

        $overall = 'pending';
        if ($directorStatus === 'rejected' || ($requiresPlanning && $planningStatus === 'rejected')) {
            $overall = 'rejected';
        } elseif ($directorStatus === 'approved' && (!$requiresPlanning || $planningStatus === 'approved')) {
            $overall = 'approved';
        }

        $request->status = $overall;
        if ($overall !== 'pending') {
            $request->decision_note = $this->consolidatedDecisionNote($request);
            $request->decided_at = now();
            $request->decided_by_user_id = $decider?->id;
        } else {
            $request->decision_note = $this->consolidatedDecisionNote($request);
            $request->decided_at = null;
            $request->decided_by_user_id = null;
        }
        $request->save();

        return $request->fresh(['requestedBy', 'decidedBy', 'directorDecidedBy', 'planningDecidedBy', 'receipts.user']);
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

    private function consolidatedDecisionNote(ProjectTransferRequest $request): ?string
    {
        $lines = [];
        if (trim((string) $request->director_note) !== '') {
            $lines[] = 'Dirección: ' . trim((string) $request->director_note);
        }
        if (trim((string) $request->planning_note) !== '') {
            $lines[] = 'Planeación AIM: ' . trim((string) $request->planning_note);
        }

        return empty($lines) ? null : implode("\n\n", $lines);
    }
}
