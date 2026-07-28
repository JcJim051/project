<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectWorkflowState extends Model
{
    protected $fillable = [
        'project_id',
        'step_id',
        'applicability_override',
        'validated_by_user_id',
        'validated_role',
        'validated_at',
        'validation_note',
    ];

    protected $casts = [
        'applicability_override' => 'boolean',
        'validated_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function step()
    {
        return $this->belongsTo(ProjectWorkflowStep::class, 'step_id');
    }

    public function validatedBy()
    {
        return $this->belongsTo(User::class, 'validated_by_user_id');
    }
}
