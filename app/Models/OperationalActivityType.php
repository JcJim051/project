<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalActivityType extends Model
{
    protected $fillable = ['codigo', 'nombre', 'descripcion', 'color', 'icono', 'orden', 'activo', 'track_as_kpi'];

    protected $casts = ['orden' => 'integer', 'activo' => 'boolean', 'track_as_kpi' => 'boolean'];

    public function activityMappings()
    {
        return $this->hasMany(OperationalActivityMapping::class);
    }
}
