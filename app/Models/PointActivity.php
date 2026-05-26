<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointActivity extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'enabled',
        'points',
        'role_scope',
        'trigger_type',
        'uniqueness_scope',
        'season_mode',
        'effective_from',
        'effective_to',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];
}
