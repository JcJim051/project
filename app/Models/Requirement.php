<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Requirement extends Model
{
    use HasFactory;

    public const EVIDENCE_RULE_PDF = 'pdf';
    public const EVIDENCE_RULE_EXCEL = 'excel';
    public const EVIDENCE_RULE_POWERPOINT = 'powerpoint';
    public const EVIDENCE_RULE_KML = 'kml';
    public const EVIDENCE_RULE_PROJECT = 'project';
    public const EVIDENCE_RULE_ANY = 'cualquiera';

    protected $fillable = [
        'source_id',
        'codigo_norma',
        'codigo_interno',
        'parent_id',
        'custom_project_id',
        'texto',
        'sector',
        'tipo',
        'requiere_check',
        'orden',
        'literal',
        'numeracion',
        'requisito',
        'nombre_documento',
        'carpeta',
        'evidence_format_rule',
        'origen',
        'visible',
    ];

    public static function evidenceFormatRuleOptions(): array
    {
        return [
            self::EVIDENCE_RULE_PDF => 'PDF',
            self::EVIDENCE_RULE_EXCEL => 'Excel',
            self::EVIDENCE_RULE_POWERPOINT => 'PowerPoint',
            self::EVIDENCE_RULE_KML => 'KML/KMZ',
            self::EVIDENCE_RULE_PROJECT => 'Project',
            self::EVIDENCE_RULE_ANY => 'Cualquiera',
        ];
    }

    public static function evidenceFormatRuleLabel(?string $rule): string
    {
        return self::evidenceFormatRuleOptions()[$rule] ?? 'Sin regla';
    }

    public function proyectos()
    {
        return $this->belongsToMany(Project::class, 'project_requirement');
    }

    public function customProject()
    {
        return $this->belongsTo(Project::class, 'custom_project_id');
    }

    public function evidences()
    {
        return $this->hasMany(RequirementEvidence::class);
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
