<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalState extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'orden',
        'color',
        'activo',
        'es_final',
        'es_bloqueante',
        'equivalente_plane',
    ];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
        'es_final' => 'boolean',
        'es_bloqueante' => 'boolean',
    ];
}
