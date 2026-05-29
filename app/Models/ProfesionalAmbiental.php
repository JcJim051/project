<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfesionalAmbiental extends Model
{
    protected $table = 'profesionales_ambientales';

    protected $fillable = ['nombre', 'correo', 'telefono', 'documento', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}

