<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectBankFinancingRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'orden',
        'producto_mga',
        'actividad',
        'valor_actividad',
        'codigo_fuente',
        'nombre_fuente',
        'meta_plan_codigo',
        'meta_plan_nombre',
        'municipio_relacion',
        'beneficiarios',
    ];

    protected $casts = [
        'orden' => 'integer',
        'valor_actividad' => 'decimal:2',
        'beneficiarios' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
