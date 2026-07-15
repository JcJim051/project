<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalActivityEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'project_id', 'plane_task_link_id', 'requirement_id', 'event_type', 'source',
        'old_value', 'new_value', 'metadata', 'occurred_at',
    ];

    protected $casts = [
        'old_value' => 'array', 'new_value' => 'array', 'metadata' => 'array', 'occurred_at' => 'datetime',
    ];

    public function project() { return $this->belongsTo(Project::class); }
    public function taskLink() { return $this->belongsTo(PlaneTaskLink::class, 'plane_task_link_id'); }
}
