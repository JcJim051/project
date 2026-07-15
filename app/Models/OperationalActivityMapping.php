<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationalActivityMapping extends Model
{
    protected $fillable = [
        'operational_module_id',
        'operational_cycle_id',
        'operational_activity_type_id',
        'requirement_id',
        'source_type',
        'source_origin',
        'source_folder',
        'repeat_per_study',
        'titulo_operativo',
        'descripcion_operativa',
        'plane_priority',
        'responsible_type',
        'planned_start_rule',
        'start_offset_days',
        'default_duration_days',
        'track_as_kpi',
        'orden',
        'activo',
        'create_automatically',
    ];

    protected $casts = [
        'repeat_per_study' => 'boolean',
        'activo' => 'boolean',
        'create_automatically' => 'boolean',
        'orden' => 'integer',
        'start_offset_days' => 'integer',
        'default_duration_days' => 'integer',
        'track_as_kpi' => 'boolean',
    ];

    public function operationalModule()
    {
        return $this->belongsTo(OperationalModule::class);
    }

    public function operationalCycle()
    {
        return $this->belongsTo(OperationalCycle::class);
    }

    public function operationalActivityType()
    {
        return $this->belongsTo(OperationalActivityType::class);
    }

    public function operationalLabels()
    {
        return $this->belongsToMany(OperationalLabel::class, 'operational_activity_mapping_label');
    }

    public function requirement()
    {
        return $this->belongsTo(Requirement::class);
    }
}
