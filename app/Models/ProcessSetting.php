<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessSetting extends Model
{
    protected $fillable = [
        'require_planning_aim_approval',
        'updated_by',
    ];

    protected $casts = [
        'require_planning_aim_approval' => 'boolean',
    ];
}
