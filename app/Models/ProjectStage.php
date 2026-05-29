<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectStage extends Model
{
    protected $fillable = ['nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}

