<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectStatus extends Model
{
    protected $fillable = ['nombre', 'orden', 'manual_allowed', 'activo'];

    protected $casts = [
        'manual_allowed' => 'boolean',
        'activo' => 'boolean',
    ];
}

