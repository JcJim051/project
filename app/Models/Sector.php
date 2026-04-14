<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sector extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
    ];

    public function proyectos()
    {
        return $this->belongsToMany(Project::class, 'project_sector');
    }
}
