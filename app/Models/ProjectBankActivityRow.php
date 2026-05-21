<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectBankActivityRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'orden',
        'producto_mga',
        'actividad',
        'valor_actividad',
        'ene',
        'feb',
        'mar',
        'abr',
        'may',
        'jun',
        'jul',
        'ago',
        'sep',
        'oct',
        'nov',
        'dic',
    ];

    protected $casts = [
        'orden' => 'integer',
        'valor_actividad' => 'decimal:2',
        'ene' => 'boolean',
        'feb' => 'boolean',
        'mar' => 'boolean',
        'abr' => 'boolean',
        'may' => 'boolean',
        'jun' => 'boolean',
        'jul' => 'boolean',
        'ago' => 'boolean',
        'sep' => 'boolean',
        'oct' => 'boolean',
        'nov' => 'boolean',
        'dic' => 'boolean',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
