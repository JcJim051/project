<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectTransferRequestReceipt extends Model
{
    protected $fillable = [
        'project_transfer_request_id',
        'user_id',
        'acknowledged_at',
        'ack_note',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
    ];

    public function transferRequest()
    {
        return $this->belongsTo(ProjectTransferRequest::class, 'project_transfer_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

