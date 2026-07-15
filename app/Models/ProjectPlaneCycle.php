<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectPlaneCycle extends Model
{
    protected $fillable = [
        'project_id', 'operational_cycle_id', 'plane_cycle_id', 'plane_project_id',
        'name_snapshot', 'start_date', 'end_date', 'status', 'sync_error', 'last_synced_at',
    ];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'last_synced_at' => 'datetime'];

    public function project() { return $this->belongsTo(Project::class); }
    public function operationalCycle() { return $this->belongsTo(OperationalCycle::class); }
}
