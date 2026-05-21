<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use HasFactory;

    protected $fillable = [
        'sector_id',
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

    public function sector()
    {
        return $this->belongsTo(Sector::class);
    }

    public function proyectos()
    {
        return $this->hasMany(Project::class);
    }

    public function getNombreConCodigoAttribute(): string
    {
        $codigo = trim((string) $this->codigo);

        return $codigo === '' ? $this->nombre : $codigo . ' - ' . $this->nombre;
    }
}
