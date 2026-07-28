<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectWorkflowState;
use App\Models\ProjectWorkflowStep;
use App\Models\Requirement;
use App\Models\RequirementEvidence;

class LicensePermitEvidenceService
{
    public function statusIsRequired(Requirement $requirement): bool
    {
        return $requirement->requiresLicensePermitClassification();
    }

    public function classificationAttributes(Requirement $requirement, ?string $status, ?int $userId): array
    {
        if (! $this->statusIsRequired($requirement)) {
            return [
                'license_permit_status' => null,
                'classified_by_user_id' => null,
                'classified_at' => null,
            ];
        }

        if (! RequirementEvidence::isValidLicensePermitStatus($status)) {
            throw new \InvalidArgumentException('Debes indicar si el documento es una solicitud o radicado, o una licencia o permiso expedido.');
        }

        return [
            'license_permit_status' => $status,
            'classified_by_user_id' => $userId,
            'classified_at' => now(),
        ];
    }

    public function invalidateWorkflowValidation(Project $project): void
    {
        $stepIds = ProjectWorkflowStep::query()
            ->where('completion_rule', ProjectWorkflowStep::COMPLETION_RULE_LICENSE_PERMIT_DEFINITIVES)
            ->whereHas('stage', fn ($query) => $query->where('funding_source', $project->funding_source ?: 'sgr'))
            ->pluck('id');

        ProjectWorkflowState::query()
            ->where('project_id', $project->id)
            ->whereIn('step_id', $stepIds)
            ->update([
                'validated_by_user_id' => null,
                'validated_role' => null,
                'validated_at' => null,
                'validation_note' => null,
            ]);
    }
}
