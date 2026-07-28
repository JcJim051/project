<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectWorkflowStep extends Model
{
    public const COMPLETION_RULE_LICENSE_PERMIT_DEFINITIVES = 'license_permit_definitives';

    protected $fillable = [
        'stage_id',
        'name',
        'slug',
        'description',
        'completion_rule',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function stage()
    {
        return $this->belongsTo(ProjectWorkflowStage::class, 'stage_id');
    }

    public function requirementLinks()
    {
        return $this->hasMany(ProjectWorkflowStepRequirement::class, 'step_id')->orderBy('sort_order');
    }

    public function requirements()
    {
        return $this->belongsToMany(
            Requirement::class,
            'project_workflow_step_requirements',
            'step_id',
            'requirement_id'
        )->withPivot(['is_required', 'sort_order'])->withTimestamps();
    }

    public function states()
    {
        return $this->hasMany(ProjectWorkflowState::class, 'step_id');
    }

    public function hasHistoricalStates(): bool
    {
        return $this->states()->exists();
    }

    public function canBeDeletedSafely(): bool
    {
        return ! $this->hasHistoricalStates();
    }

    public static function completionRuleOptions(): array
    {
        return [
            self::COMPLETION_RULE_LICENSE_PERMIT_DEFINITIVES => 'Definitivos de licencias y permisos',
        ];
    }
}
