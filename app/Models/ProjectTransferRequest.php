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
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'decided_at' => 'datetime',
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

    public function receipts()
    {
        return $this->hasMany(ProjectTransferRequestReceipt::class);
    }

    public function requirementComments()
    {
        return $this->hasMany(ProjectTransferRequestRequirementComment::class);
    }
}
