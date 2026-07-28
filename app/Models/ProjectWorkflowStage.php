<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectWorkflowStage extends Model
{
    protected $fillable = [
        'funding_source',
        'name',
        'slug',
        'sort_order',
        'is_optional',
        'optional_rule',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_optional' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function steps()
    {
        return $this->hasMany(ProjectWorkflowStep::class, 'stage_id')->orderBy('sort_order');
    }

    public function hasHistoricalStates(): bool
    {
        return $this->steps()->whereHas('states')->exists();
    }

    public function canBeDeletedSafely(): bool
    {
        return ! $this->hasHistoricalStates();
    }
}
