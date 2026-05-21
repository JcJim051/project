<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectBankSignatory extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'role',
        'orden',
        'nombre',
        'cargo',
        'correo',
        'telefono',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
