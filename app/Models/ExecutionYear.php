<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExecutionYear extends Model
{
    protected $table = 'execution_years';

    protected $fillable = ['anio', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}

