<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalCycle extends Model
{
    protected $fillable = [
        'codigo', 'nombre', 'descripcion', 'orden', 'activo', 'anchor_type',
        'start_offset_days', 'duration_days', 'fixed_start_date', 'fixed_end_date',
        'owner_role', 'timezone',
    ];

    protected $casts = [
        'orden' => 'integer',
        'activo' => 'boolean',
        'start_offset_days' => 'integer',
        'duration_days' => 'integer',
        'fixed_start_date' => 'date',
        'fixed_end_date' => 'date',
    ];

    public function activityMappings()
    {
        return $this->hasMany(OperationalActivityMapping::class);
    }
}
