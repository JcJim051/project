<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'plane_project_id',
        'plane_project_url',
        'plane_sync_status',
        'plane_last_provisioned_at',
        'plane_states_seeded_at',
        'plane_last_error',
        'plane_connection_id',
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
        'plane_last_provisioned_at' => 'datetime',
        'plane_states_seeded_at' => 'datetime',
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
        return $this->belongsToMany(Requirement::class, 'project_requirement')
            ->withPivot('activated_at')
            ->withTimestamps();
    }

    public function evidences()
    {
        return $this->hasMany(RequirementEvidence::class);
    }

    public function attachmentPackageRuns()
    {
        return $this->hasMany(AttachmentPackageRun::class);
    }

    public function planeConnection()
    {
        return $this->belongsTo(PlaneConnection::class, 'plane_connection_id');
    }

    public function planeTaskLinks()
    {
        return $this->hasMany(PlaneTaskLink::class);
    }

    public function planeCycles()
    {
        return $this->hasMany(ProjectPlaneCycle::class);
    }

    public function planeLabels()
    {
        return $this->hasMany(ProjectPlaneLabel::class);
    }

    public function operationalActivityEvents()
    {
        return $this->hasMany(OperationalActivityEvent::class);
    }

    public function planeSyncRuns()
    {
        return $this->hasMany(PlaneSyncRun::class)->latest('id');
    }

    public function studySpecialistAssignments()
    {
        return $this->hasMany(ProjectStudySpecialistAssignment::class)->orderBy('study_folder');
    }

    public function getResolvedPlaneProjectUrlAttribute(): ?string
    {
        $savedUrl = preg_replace('#\s+/#', '/', trim((string) ($this->plane_project_url ?? ''))) ?? trim((string) ($this->plane_project_url ?? ''));
        $savedUrl = preg_replace('#/\s+#', '/', $savedUrl) ?? $savedUrl;

        if (blank($this->plane_project_id)) {
            return $savedUrl !== '' ? $savedUrl : null;
        }

        if ($savedUrl !== '' && Str::contains($savedUrl, '/issues/')) {
            return $savedUrl;
        }

        $connection = $this->relationLoaded('planeConnection')
            ? $this->planeConnection
            : ($this->planeConnection()->first() ?: PlaneConnection::query()->where('activo', true)->latest('id')->first());

        if (! $connection) {
            return $savedUrl !== '' ? $savedUrl : null;
        }

        $template = trim((string) ($connection->project_url_template ?? ''));
        if ($template === '') {
            $template = '/{workspace_slug}/projects/{project_id}/issues/';
        }

        if (! Str::startsWith($template, ['http://', 'https://'])) {
            $template = '/' . ltrim($template, '/');

            if (! Str::contains($template, '{workspace_slug}')) {
                if (Str::startsWith($template, '/projects/')) {
                    $template = '/{workspace_slug}' . $template;
                } else {
                    $template = '/{workspace_slug}/' . ltrim($template, '/');
                }
            }

            if (preg_match('#/\{project_id\}/?$#', $template) === 1 && ! Str::contains($template, '/issues')) {
                $template = rtrim($template, '/') . '/issues/';
            }

            $template = preg_replace('#\s+/#', '/', $template) ?? $template;
            $template = preg_replace('#/\s+#', '/', $template) ?? $template;

            return rtrim((string) $connection->url_base, '/') . '/' . ltrim(strtr($template, [
                '{workspace_slug}' => (string) $connection->workspace_id,
                '{project_id}' => (string) $this->plane_project_id,
            ]), '/');
        }

        $template = preg_replace('#\s+/#', '/', $template) ?? $template;
        $template = preg_replace('#/\s+#', '/', $template) ?? $template;

        return strtr($template, [
            '{workspace_slug}' => (string) $connection->workspace_id,
            '{project_id}' => (string) $this->plane_project_id,
        ]);
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
