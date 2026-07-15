<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalLabel extends Model
{
    protected $fillable = ['codigo', 'nombre', 'descripcion', 'color', 'orden', 'activo'];

    protected $casts = ['orden' => 'integer', 'activo' => 'boolean'];

    public function activityMappings()
    {
        return $this->belongsToMany(OperationalActivityMapping::class, 'operational_activity_mapping_label');
    }
}
