<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTransferRequest extends Model
{
    protected $fillable = [
        'project_id',
        'requested_by_user_id',
        'status',
        'request_note',
        'decision_note',
        'requested_at',
        'decided_at',
        'decided_by_user_id',
        'director_status',
        'director_note',
        'director_decided_at',
        'director_decided_by_user_id',
        'planning_status',
        'planning_note',
        'planning_decided_at',
        'planning_decided_by_user_id',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
        'director_decided_at' => 'datetime',
        'planning_decided_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function requestedBy()
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function directorDecidedBy()
    {
        return $this->belongsTo(User::class, 'director_decided_by_user_id');
    }

    public function planningDecidedBy()
    {
        return $this->belongsTo(User::class, 'planning_decided_by_user_id');
    }

    public function receipts()
    {
        return $this->hasMany(ProjectTransferRequestReceipt::class);
    }

    public function requirementComments()
    {
        return $this->hasMany(ProjectTransferRequestRequirementComment::class);
    }

    public function directorApproved(): bool
    {
        return (string) ($this->director_status ?: $this->status) === 'approved';
    }

    public function planningApproved(): bool
    {
        return (string) $this->planning_status === 'approved';
    }

    public function approvalComplete(bool $requiresPlanning): bool
    {
        return $this->directorApproved() && (!$requiresPlanning || $this->planningApproved());
    }
}
