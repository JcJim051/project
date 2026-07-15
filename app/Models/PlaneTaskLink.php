<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaneTaskLink extends Model
{
    protected $fillable = [
        'project_id',
        'operational_module_id',
        'operational_cycle_id',
        'operational_activity_type_id',
        'operational_activity_mapping_id',
        'requirement_id',
        'plane_project_id',
        'plane_issue_id',
        'plane_module_id',
        'dedupe_key',
        'source_type',
        'source_origin',
        'source_folder',
        'source_title',
        'title',
        'plane_priority',
        'responsible_type',
        'resolved_user_id',
        'resolved_user_email',
        'resolved_plane_assignee_id',
        'assignment_note',
        'activated_at',
        'planned_start_date',
        'planned_target_date',
        'plane_cycle_id',
        'plane_label_ids',
        'current_state_code',
        'first_started_at',
        'completed_at',
        'deactivated_at',
        'status',
        'sync_error',
        'last_synced_at',
    ];

    protected $casts = [
        'last_synced_at' => 'datetime',
        'activated_at' => 'datetime',
        'planned_start_date' => 'date',
        'planned_target_date' => 'date',
        'plane_label_ids' => 'array',
        'first_started_at' => 'datetime',
        'completed_at' => 'datetime',
        'deactivated_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function operationalModule()
    {
        return $this->belongsTo(OperationalModule::class);
    }

    public function mapping()
    {
        return $this->belongsTo(OperationalActivityMapping::class, 'operational_activity_mapping_id');
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }
}
