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
        'funding_source',
        'valor',
        'producto_id',
        'nombre_clave',
        'municipio',
        'secretaria',
        'ruta_drive',
        'drive_folder_id',
        'attachments_min_percent',
        'attachment_package_selection',
        'formulador_id',
        'estructurador_id',
        'prioridad_entidad_id',
        'prioridad_estructurador',
        'profesional_ambiental_id',
        'project_stage_id',
        'project_status_id',
        'duracion_meses',
        'poblacion_objetivo',
        'fecha_creacion',
    ];

    protected $casts = [
        'fecha_creacion' => 'date',
        'valor' => 'decimal:2',
        'attachments_min_percent' => 'integer',
        'attachment_package_selection' => 'array',
        'duracion_meses' => 'integer',
        'poblacion_objetivo' => 'integer',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }

    public function sectores()
    {
        return $this->belongsToMany(Sector::class, 'project_sector')
            ->withPivot('is_primary');
    }

    public function municipios()
    {
        return $this->belongsToMany(Municipio::class, 'municipio_project');
    }

    public function executionYears()
    {
        return $this->belongsToMany(ExecutionYear::class, 'execution_year_project');
    }

    public function getMunicipiosDisplayAttribute(): string
    {
        if ($this->relationLoaded('municipios') && $this->municipios->isNotEmpty()) {
            return $this->municipios->pluck('nombre')->implode(', ');
        }

        return (string) $this->municipio;
    }

    public function formulador()
    {
        return $this->belongsTo(User::class, 'formulador_id');
    }

    public function estructurador()
    {
        return $this->belongsTo(User::class, 'estructurador_id');
    }

    public function prioridadEntidad()
    {
        return $this->belongsTo(PrioridadEntidad::class, 'prioridad_entidad_id');
    }

    public function profesionalAmbiental()
    {
        return $this->belongsTo(ProfesionalAmbiental::class, 'profesional_ambiental_id');
    }

    public function stage()
    {
        return $this->belongsTo(ProjectStage::class, 'project_stage_id');
    }

    public function status()
    {
        return $this->belongsTo(ProjectStatus::class, 'project_status_id');
    }

    public function requisitos()
    {
        return $this->belongsToMany(Requirement::class, 'project_requirement');
    }

    public function evidences()
    {
        return $this->hasMany(RequirementEvidence::class);
    }

    public function attachmentPackageRuns()
    {
        return $this->hasMany(AttachmentPackageRun::class);
    }

    public function bankProfile()
    {
        return $this->hasOne(ProjectBankProfile::class);
    }

    public function bankSignatories()
    {
        return $this->hasMany(ProjectBankSignatory::class)->orderBy('orden');
    }

    public function bankFinancingRows()
    {
        return $this->hasMany(ProjectBankFinancingRow::class)->orderBy('orden');
    }

    public function bankActivityRows()
    {
        return $this->hasMany(ProjectBankActivityRow::class)->orderBy('orden');
    }

    public function transferRequests()
    {
        return $this->hasMany(ProjectTransferRequest::class)->latest('id');
    }
}
