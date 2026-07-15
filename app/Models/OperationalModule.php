<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalModule extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'orden',
        'descripcion',
        'activo',
        'crea_tareas',
        'color',
        'icono',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'crea_tareas' => 'boolean',
        'orden' => 'integer',
    ];

    public function activityMappings()
    {
        return $this->hasMany(OperationalActivityMapping::class)->orderBy('orden');
    }
}
