<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectWorkflowStage;
use App\Models\ProjectWorkflowState;
use App\Models\ProjectWorkflowStep;
use App\Models\RequirementEvidence;
use Illuminate\Support\Collection;

class ProjectWorkflowService
{
    public function buildForProject(Project $project): Collection
    {
        $project->loadMissing('executionYears');

        $stages = ProjectWorkflowStage::query()
            ->where('funding_source', $project->funding_source ?: 'sgr')
            ->where('is_active', true)
            ->with([
                'steps' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['requirementLinks.requirement', 'states' => fn ($stateQuery) => $stateQuery
                        ->where('project_id', $project->id)
                        ->with('validatedBy')]),
            ])
            ->orderBy('sort_order')
            ->get();

        $requirementIds = $stages
            ->flatMap->steps
            ->reject(fn ($step) => $step->completion_rule === ProjectWorkflowStep::COMPLETION_RULE_LICENSE_PERMIT_DEFINITIVES)
            ->flatMap->requirementLinks
            ->pluck('requirement_id')
            ->filter()
            ->unique()
            ->values();

        $licenseRequirements = $project->requisitos()
            ->where('requirements.visible', true)
            ->get()
            ->filter(fn ($requirement) => $requirement->requiresLicensePermitClassification())
            ->values();
        $allEvidenceRequirementIds = $requirementIds
            ->merge($licenseRequirements->pluck('id'))
            ->unique()
            ->values();

        if ($requirementIds->isNotEmpty()) {
            $project->requisitos()->syncWithoutDetaching(
                $requirementIds->mapWithKeys(fn ($id) => [(int) $id => ['activated_at' => now()]])->all()
            );
        }

        $evidences = RequirementEvidence::query()
            ->where('project_id', $project->id)
            ->whereIn('requirement_id', $allEvidenceRequirementIds)
            ->where('in_drive', true)
            ->get()
            ->groupBy('requirement_id');

        return $stages->map(function ($stage) use ($project, $evidences, $licenseRequirements) {
            $steps = $stage->steps->map(function ($step) use ($project, $stage, $evidences, $licenseRequirements) {
                $state = $step->states->first();
                $applicable = $state?->applicability_override;

                if ($step->completion_rule === ProjectWorkflowStep::COMPLETION_RULE_LICENSE_PERMIT_DEFINITIVES) {
                    return $this->buildLicensePermitStep(
                        $step,
                        $state,
                        $licenseRequirements,
                        $evidences,
                        $applicable
                    );
                }

                if ($applicable === null) {
                    $applicable = $this->defaultApplicability($project, $stage);
                }

                $requiredLinks = $step->requirementLinks->where('is_required', true);
                $complete = $requiredLinks->isNotEmpty()
                    && $requiredLinks->every(fn ($link) => $evidences->get($link->requirement_id, collect())->isNotEmpty());
                $validated = $complete && (bool) ($state?->validated_at);

                return [
                    'model' => $step,
                    'state_model' => $state,
                    'name' => $step->name,
                    'description' => $step->description,
                    'applicable' => (bool) $applicable,
                    'complete' => $complete,
                    'validated' => $validated,
                    'status' => ! $applicable ? 'not_applicable' : ($validated ? 'validated' : ($complete ? 'completed' : 'pending')),
                    'requirements' => $step->requirementLinks->map(function ($link) use ($evidences) {
                        return [
                            'model' => $link->requirement,
                            'required' => (bool) $link->is_required,
                            'complete' => $evidences->get($link->requirement_id, collect())->isNotEmpty(),
                            'evidences' => $evidences->get($link->requirement_id, collect())->values(),
                        ];
                    })->values(),
                ];
            })->values();

            $applicableSteps = $steps->where('applicable', true);
            $validated = $applicableSteps->isNotEmpty() && $applicableSteps->every(fn ($step) => $step['validated']);
            $complete = $applicableSteps->isNotEmpty() && $applicableSteps->every(fn ($step) => $step['complete']);
            $nothingApplies = $steps->isNotEmpty() && $applicableSteps->isEmpty();

            return [
                'model' => $stage,
                'name' => $stage->name,
                'steps' => $steps,
                'applicable' => ! $nothingApplies,
                'status' => $nothingApplies
                    ? 'not_applicable'
                    : ($validated ? 'validated' : ($complete ? 'completed' : 'pending')),
                'percent' => $applicableSteps->isNotEmpty()
                    ? (int) round(($applicableSteps->where('complete', true)->count() / $applicableSteps->count()) * 100)
                    : 0,
            ];
        })->values();
    }

    private function buildLicensePermitStep(
        ProjectWorkflowStep $step,
        $state,
        Collection $requirements,
        Collection $evidences,
        ?bool $applicabilityOverride
    ): array {
        $requirementRows = $requirements->map(function ($requirement) use ($evidences) {
            $rows = $evidences->get($requirement->id, collect())->values();
            $classified = $rows->filter(fn ($evidence) => RequirementEvidence::isValidLicensePermitStatus(
                $evidence->license_permit_status
            ));
            $hasIssued = $classified->contains(fn ($evidence) => $evidence->license_permit_status === RequirementEvidence::LICENSE_PERMIT_ISSUED);
            $hasApplication = $classified->contains(fn ($evidence) => $evidence->license_permit_status === RequirementEvidence::LICENSE_PERMIT_APPLICATION);

            return [
                'model' => $requirement,
                'required' => true,
                'complete' => $hasIssued,
                'evidences' => $rows,
                'license_permit_follow_up' => true,
                'follow_up_status' => $hasIssued
                    ? 'definitive_loaded'
                    : ($hasApplication ? 'definitive_pending' : 'pending_structure'),
            ];
        })->values();

        $applicable = $applicabilityOverride ?? $requirementRows->isNotEmpty();
        $complete = $applicable
            && $requirementRows->isNotEmpty()
            && $requirementRows->every(fn ($row) => $row['complete']);
        $validated = $complete && (bool) ($state?->validated_at);

        return [
            'model' => $step,
            'state_model' => $state,
            'name' => $step->name,
            'description' => $step->description,
            'applicable' => (bool) $applicable,
            'complete' => $complete,
            'validated' => $validated,
            'status' => ! $applicable ? 'not_applicable' : ($validated ? 'validated' : ($complete ? 'completed' : 'pending')),
            'requirements' => $requirementRows,
            'license_permit_follow_up' => true,
        ];
    }

    public function validateStep(Project $project, int $stepId, $user, ?string $note): ProjectWorkflowState
    {
        if (! $user || ! ($user->isAdminUser() || $user->hasAnyRole(['director', 'planeacion_aim']))) {
            abort(403, 'No tienes permiso para validar etapas.');
        }

        $stepStatus = $this->buildForProject($project)
            ->flatMap(fn ($stage) => $stage['steps'])
            ->first(fn ($step) => (int) $step['model']->id === $stepId);
        if (! $stepStatus || ! $stepStatus['applicable'] || ! $stepStatus['complete']) {
            abort(422, 'El elemento debe estar completo antes de validarse.');
        }

        $role = $user->hasRole('planeacion_aim') && ! $user->hasRole('director')
            ? 'planeacion_aim'
            : 'director';

        return ProjectWorkflowState::query()->updateOrCreate(
            ['project_id' => $project->id, 'step_id' => $stepId],
            [
                'validated_by_user_id' => $user->id,
                'validated_role' => $role,
                'validated_at' => now(),
                'validation_note' => $note,
            ]
        );
    }

    public function clearValidation(Project $project, int $stepId, $user): ProjectWorkflowState
    {
        if (! $user || ! ($user->isAdminUser() || $user->hasAnyRole(['director', 'planeacion_aim']))) {
            abort(403, 'No tienes permiso para retirar validaciones.');
        }

        $state = ProjectWorkflowState::query()->firstOrCreate([
            'project_id' => $project->id,
            'step_id' => $stepId,
        ]);
        $state->forceFill([
            'validated_by_user_id' => null,
            'validated_role' => null,
            'validated_at' => null,
            'validation_note' => null,
        ])->save();

        return $state;
    }

    public function setApplicability(Project $project, int $stepId, ?bool $applicable, $user): ProjectWorkflowState
    {
        if (! $user?->isAdminUser()) {
            abort(403, 'Solo administración puede cambiar la aplicabilidad.');
        }

        return ProjectWorkflowState::query()->updateOrCreate(
            ['project_id' => $project->id, 'step_id' => $stepId],
            ['applicability_override' => $applicable]
        );
    }

    private function defaultApplicability(Project $project, $stage): bool
    {
        if ($stage->optional_rule !== 'multiple_execution_years') {
            return true;
        }

        return $project->executionYears->count() > 1;
    }
}
