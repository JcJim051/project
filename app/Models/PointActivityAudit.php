<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointActivityAudit extends Model
{
    protected $fillable = [
        'point_activity_id',
        'changed_by',
        'action',
        'before',
        'after',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];
}

