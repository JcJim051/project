<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Specialist extends Model
{
    protected $fillable = [
        'nombre',
        'correo',
        'documento',
        'telefono',
        'especialidad',
        'notas',
        'activo',
        'plane_user_id',
        'plane_sync_status',
        'plane_last_synced_at',
        'plane_last_error',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'plane_last_synced_at' => 'datetime',
    ];

    public function studyAssignments()
    {
        return $this->hasMany(ProjectStudySpecialistAssignment::class);
    }
}
