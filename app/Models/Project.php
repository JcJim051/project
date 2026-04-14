<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'objeto_proyecto',
        'id_proyecto',
        'bipin',
        'nombre_clave',
        'municipio',
        'secretaria',
        'ruta_drive',
        'drive_folder_id',
        'formulador_id',
        'estructurador_id',
        'fecha_creacion',
    ];

    protected $casts = [
        'fecha_creacion' => 'date',
    ];

    public function sectores()
    {
        return $this->belongsToMany(Sector::class, 'project_sector');
    }

    public function formulador()
    {
        return $this->belongsTo(User::class, 'formulador_id');
    }

    public function estructurador()
    {
        return $this->belongsTo(User::class, 'estructurador_id');
    }

    public function requisitos()
    {
        return $this->belongsToMany(Requirement::class, 'project_requirement');
    }

    public function evidences()
    {
        return $this->hasMany(RequirementEvidence::class);
    }
}
