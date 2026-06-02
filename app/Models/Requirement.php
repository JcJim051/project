<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_id',
        'codigo_norma',
        'codigo_interno',
        'parent_id',
        'custom_project_id',
        'texto',
        'sector',
        'tipo',
        'requiere_check',
        'orden',
        'literal',
        'numeracion',
        'requisito',
        'nombre_documento',
        'carpeta',
        'origen',
        'visible',
    ];

    public function proyectos()
    {
        return $this->belongsToMany(Project::class, 'project_requirement');
    }

    public function customProject()
    {
        return $this->belongsTo(Project::class, 'custom_project_id');
    }

    public function evidences()
    {
        return $this->hasMany(RequirementEvidence::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
