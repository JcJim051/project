<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlaneSyncRun extends Model
{
    protected $fillable = [
        'project_id',
        'initiated_by_user_id',
        'mode',
        'status',
        'attempt_count',
        'job_unique_key',
        'message',
        'error_details',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function initiatedBy()
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
}
