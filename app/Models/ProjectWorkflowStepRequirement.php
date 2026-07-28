<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectWorkflowStepRequirement extends Model
{
    protected $fillable = [
        'step_id',
        'requirement_id',
        'is_required',
        'sort_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function step()
    {
        return $this->belongsTo(ProjectWorkflowStep::class, 'step_id');
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }
}
