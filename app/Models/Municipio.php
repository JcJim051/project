<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function proyectos()
    {
        return $this->belongsToMany(Project::class, 'municipio_project');
    }

    public function tipos()
    {
        return $this->belongsToMany(MunicipioTipo::class, 'municipio_municipio_tipo');
    }
}
