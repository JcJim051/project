<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectBankProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'horizonte_anio_0',
        'horizonte_anio_1',
        'horizonte_anio_2',
        'horizonte_anio_3',
        'tipo_presentacion',
        'tipo_tramite',
        'codigo_dependencia',
        'dependencia',
        'vigencia',
        'proyecto_titulo_override',
        'pilar',
        'eje',
        'linea',
        'programa',
        'subprograma',
        'codigo_fuente',
        'nombre_fuente',
        'meta_plan_codigo',
        'meta_plan_nombre',
        'municipio_relacion',
        'beneficiarios',
        'sector_texto_plantilla',
        'observaciones',
    ];

    protected $casts = [
        'horizonte_anio_0' => 'integer',
        'horizonte_anio_1' => 'integer',
        'horizonte_anio_2' => 'integer',
        'horizonte_anio_3' => 'integer',
        'vigencia' => 'integer',
        'beneficiarios' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
