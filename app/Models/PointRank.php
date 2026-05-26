<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointRank extends Model
{
    protected $fillable = [
        'level_order',
        'name',
        'min_points',
        'image_path',
        'enabled',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}

