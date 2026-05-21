<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PlanDevelopmentCatalogItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'pilar_codigo',
        'pilar',
        'eje_codigo',
        'eje',
        'linea_codigo',
        'linea',
        'programa_codigo',
        'programa',
        'subprograma_codigo',
        'subprograma',
        'sector_codigo',
        'sector',
        'codigo_meta_plan',
        'nombre_meta_plan',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];
}
