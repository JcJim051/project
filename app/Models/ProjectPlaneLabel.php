<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectPlaneLabel extends Model
{
    protected $fillable = [
        'project_id', 'operational_label_id', 'plane_label_id', 'plane_project_id',
        'name_snapshot', 'status', 'sync_error', 'last_synced_at',
    ];

    protected $casts = ['last_synced_at' => 'datetime'];

    public function project() { return $this->belongsTo(Project::class); }
    public function operationalLabel() { return $this->belongsTo(OperationalLabel::class); }
}
