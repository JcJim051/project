<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    use HasFactory;

    protected $fillable = [
        'codigo',
        'nombre',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    protected $appends = [
        'nombre_con_codigo',
    ];

    public function proyectos()
    {
        return $this->belongsToMany(Project::class, 'project_sector');
    }

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }

    public function getNombreConCodigoAttribute(): string
    {
        $codigo = trim((string) $this->codigo);

        return $codigo === '' ? $this->nombre : $codigo . ' - ' . $this->nombre;
    }
}
