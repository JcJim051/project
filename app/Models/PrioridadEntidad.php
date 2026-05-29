<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrioridadEntidad extends Model
{
    protected $table = 'prioridad_entidad_catalogo';

    protected $fillable = ['numero', 'nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}

