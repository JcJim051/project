<?php

namespace App\Services;

use App\Models\OperationalActivityMapping;
use App\Models\OperationalModule;
use App\Models\Project;
use App\Models\Requirement;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class OperationalActivityMappingService
{
    public function ensureDefaults(): void
    {
        $this->syncDefaultRequirementMappings();
    }

    public function applicableRequirements(Project $project): Collection
    {
        $project->loadMissing('sectores');

        $requirements = Requirement::query()
            ->where('visible', true)
            ->where(function ($query) use ($project) {
                $query->whereNull('custom_project_id')
                    ->orWhere('custom_project_id', $project->id);
            })
            ->whereIn('id', $project->requisitos()->pluck('requirements.id'))
            ->orderBy('carpeta')
            ->orderByRaw('custom_project_id IS NOT NULL')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->get();

        return $this->filterSectorial($requirements, $project)->values();
    }

    public function applicableStudyFolders(Project $project): Collection
    {
        return $this->applicableRequirements($project)
            ->filter(fn (Requirement $requirement) => $this->isStudyFolder((string) $requirement->carpeta))
            ->pluck('carpeta')
            ->filter()
            ->unique()
            ->values();
    }

    public function resolveModuleForRequirement(Requirement $requirement): ?OperationalModule
    {
        $moduleCode = $this->resolveModuleCode($requirement);

        return $moduleCode
            ? OperationalModule::query()->where('codigo', $moduleCode)->first()
            : null;
    }

    public function titleForRequirement(Requirement $requirement, OperationalModule $module): string
    {
        $name = trim((string) ($requirement->nombre_documento ?: $requirement->requisito ?: $requirement->texto ?: 'Actividad'));
        $normalized = Str::lower(Str::ascii($name));

        return $this->truncateTitle(match ($module->codigo) {
            '01' => $this->titleForKickoffRequirement($name, $normalized),
            '03' => $this->titleForBudgetRequirement($name),
            '04' => $this->titleForFormulationRequirement($name, $normalized),
            '05' => $this->titleForTechnicalRequirement($name, $normalized),
            '06' => $this->titleForLicenseRequirement($name, $normalized),
            '07' => $this->titleForCertificationRequirement($name, $normalized),
            '09' => $this->titleForReviewRequirement($name, $normalized),
            default => 'Gestionar ' . Str::lower($name),
        });
    }

    public function descriptionForRequirement(Requirement $requirement, OperationalModule $module): string
    {
        $base = match ($module->codigo) {
            '01' => 'Definir el insumo de arranque y dejarlo listo para seguimiento operativo.',
            '02' => 'Desarrollar la actividad del estudio y dejar soporte listo para validación.',
            '03' => 'Consolidar el componente presupuestal y dejar soporte listo para validación.',
            '04' => 'Gestionar el componente MGA y dejar soporte listo para validación.',
            '05' => 'Consolidar el entregable técnico y dejar soporte listo para validación.',
            '06' => 'Gestionar el permiso o licencia y dejar soporte listo para validación.',
            '07' => 'Gestionar la certificación y dejar soporte listo para validación.',
            '09' => 'Gestionar la revisión interna y dejar soporte listo para validación.',
            default => 'Gestionar la actividad y dejar soporte listo para validación en Orbit.',
        };

        if ($module->codigo === '02') {
            return $base . ' Estudio: ' . ($requirement->carpeta ?: 'Sin estudio') . '.';
        }

        return $base;
    }

    private function syncDefaultRequirementMappings(): void
    {
        $requirements = Requirement::query()
            ->where('visible', true)
            ->orderBy('carpeta')
            ->orderByRaw('custom_project_id IS NOT NULL')
            ->orderBy('orden')
            ->orderBy('codigo_interno')
            ->orderBy('nombre_documento')
            ->get();

        foreach ($requirements as $requirement) {
            $module = $this->resolveModuleForRequirement($requirement);
            if (! $module) {
                continue;
            }

            $defaults = [
                'operational_module_id' => $module->id,
                'source_type' => 'requirement',
                'source_origin' => $requirement->custom_project_id ? 'manual' : 'catalog',
                'source_folder' => $requirement->carpeta,
                'repeat_per_study' => false,
                'titulo_operativo' => $this->titleForRequirement($requirement, $module),
                'descripcion_operativa' => $this->descriptionForRequirement($requirement, $module),
                'plane_priority' => 'medium',
                'responsible_type' => $this->defaultResponsibleTypeForModule($module->codigo),
                'planned_start_rule' => 'none',
                'start_offset_days' => 0,
                'orden' => (int) ($requirement->orden ?? 0),
                'activo' => true,
                'create_automatically' => true,
            ];

            $mapping = OperationalActivityMapping::query()->firstOrCreate(
                ['requirement_id' => $requirement->id],
                $defaults
            );

            $updates = [
                'source_origin' => $requirement->custom_project_id ? 'manual' : 'catalog',
                'source_folder' => $requirement->carpeta,
            ];

            if (blank($mapping->plane_priority)) {
                $updates['plane_priority'] = 'medium';
            }
            if (blank($mapping->planned_start_rule)) {
                $updates['planned_start_rule'] = 'none';
            }
            $defaultResponsibleType = $this->defaultResponsibleTypeForModule($module->codigo);
            if (
                blank($mapping->responsible_type)
                || ($mapping->responsible_type === 'sin_responsable' && $defaultResponsibleType !== 'sin_responsable')
            ) {
                $updates['responsible_type'] = $defaultResponsibleType;
            }

            if (! empty($updates)) {
                $mapping->forceFill($updates)->save();
            }
        }
    }

    private function defaultResponsibleTypeForModule(string $moduleCode): string
    {
        return match ($moduleCode) {
            '02' => 'especialista_estudio',
            '03', '06', '07', '09' => 'estructurador',
            '04', '05' => 'formulador',
            default => 'sin_responsable',
        };
    }

    private function resolveModuleCode(Requirement $requirement): ?string
    {
        $folder = (string) ($requirement->carpeta ?? '');
        $code = trim((string) ($requirement->codigo_interno ?? ''));
        $normalizedFolder = Str::lower(Str::ascii($folder));

        if ($folder === '04 Licencias y Permisos') {
            return '06';
        }

        if (in_array($folder, [
            '3.1 Certificaciones Generales',
            '3.2 Certificaciones Generales Adicionales',
            '3.3 Otras Certificaciones',
            '3.4 Documentos Sectoriales',
        ], true)) {
            return '07';
        }

        if ($this->isStudyFolder($folder)) {
            return '02';
        }

        if ($folder === '02 Presupuesto' || preg_match('/^2\./', $folder)) {
            return '03';
        }

        if ($folder !== '01 Formulacion' && ! str_contains($normalizedFolder, 'formulacion')) {
            return null;
        }

        if (preg_match('/^1\.13\s*0?(2|3)\b/', $code)) {
            return '04';
        }

        if (preg_match('/^1\.13\s*0?(1|4|5)\b/', $code)) {
            return '09';
        }

        if (preg_match('/^1\.02\b/', $code) || preg_match('/^1\.11\b/', $code)) {
            return '05';
        }

        if (preg_match('/^1\.01\b/', $code)
            || preg_match('/^1\.03\b/', $code)
            || preg_match('/^1\.04\b/', $code)
            || preg_match('/^1\.05\b/', $code)
            || preg_match('/^1\.07\b/', $code)
            || preg_match('/^1\.08\b/', $code)
            || preg_match('/^1\.09\b/', $code)
            || preg_match('/^1\.06\s*0?(2|3|4|5)\b/', $code)) {
            return '04';
        }

        if ($code === '1.1'
            || preg_match('/^1\.06\s*0?(0|1|6)\b/', $code)
            || preg_match('/^1\.12\b/', $code)) {
            return '01';
        }

        return '04';
    }

    private function titleForKickoffRequirement(string $name, string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'socializacion') => 'Socializar proyecto',
            str_contains($normalized, 'hoja de control') => 'Organizar hoja de control documental',
            str_contains($normalized, 'objeto') => 'Definir objeto del proyecto',
            str_contains($normalized, 'meta') || str_contains($normalized, 'producto') => 'Seleccionar meta y producto MGA',
            default => 'Preparar ' . Str::lower($name),
        };
    }

    private function titleForBudgetRequirement(string $name): string
    {
        return 'Consolidar ' . Str::lower($name);
    }

    private function titleForFormulationRequirement(string $name, string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'mga') => 'Actualizar MGA',
            str_contains($normalized, 'metodologia') => 'Actualizar metodología MGA',
            str_contains($normalized, 'radicacion') => 'Preparar radicación MGA',
            default => 'Gestionar ' . Str::lower($name),
        };
    }

    private function titleForTechnicalRequirement(string $name, string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'documento tecnico') => 'Consolidar documento técnico',
            str_contains($normalized, 'diagnostico') => 'Consolidar diagnóstico técnico',
            default => 'Preparar ' . Str::lower($name),
        };
    }

    private function titleForLicenseRequirement(string $name, string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'licencia') => 'Tramitar ' . Str::lower($name),
            str_contains($normalized, 'permiso') => 'Tramitar ' . Str::lower($name),
            default => 'Gestionar ' . Str::lower($name),
        };
    }

    private function titleForCertificationRequirement(string $name, string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'certificado') || str_contains($normalized, 'certificacion') => 'Procesar ' . Str::lower($name),
            str_contains($normalized, 'acto administrativo') => 'Gestionar acto administrativo',
            default => 'Gestionar ' . Str::lower($name),
        };
    }

    private function titleForReviewRequirement(string $name, string $normalized): string
    {
        return match (true) {
            str_contains($normalized, 'revision') => 'Consolidar revisión interna',
            str_contains($normalized, 'observacion') => 'Emitir observaciones internas',
            default => 'Revisar ' . Str::lower($name),
        };
    }

    private function truncateTitle(string $value): string
    {
        return Str::limit(trim($value), 255, '');
    }

    private function filterSectorial(Collection $requirements, Project $project): Collection
    {
        $sectorNames = $project->sectores
            ->pluck('nombre')
            ->map(fn ($value) => $this->normalizeSector((string) $value))
            ->filter()
            ->values()
            ->all();

        if (empty($sectorNames)) {
            return $requirements;
        }

        return $requirements->filter(function (Requirement $req) use ($sectorNames) {
            $folder = $this->normalizeSector((string) ($req->carpeta ?? ''));
            if (! str_contains($folder, 'documentos sectoriales')) {
                return true;
            }

            $sector = $this->normalizeSector((string) ($req->sector ?? ''));
            if ($sector === '') {
                return false;
            }

            foreach ($sectorNames as $projectSector) {
                if ($sector === $projectSector || str_contains($sector, $projectSector) || str_contains($projectSector, $sector)) {
                    return true;
                }

                $sectorTokens = collect(explode(' ', str_replace(' y ', ' ', $sector)))->filter()->sort()->values()->all();
                $projectTokens = collect(explode(' ', str_replace(' y ', ' ', $projectSector)))->filter()->sort()->values()->all();
                if ($sectorTokens === $projectTokens) {
                    return true;
                }
            }

            return false;
        });
    }

    private function normalizeSector(string $value): string
    {
        $value = trim(Str::lower(Str::ascii($value)));
        return preg_replace('/\s+/', ' ', $value) ?: '';
    }

    private function isStudyFolder(string $folder): bool
    {
        $normalized = $this->normalizeSector($folder);

        return preg_match('/^5\./', $folder)
            || preg_match('/^05\b/', $folder)
            || str_contains($normalized, 'diseno')
            || str_contains($normalized, 'estudio')
            || str_contains($normalized, 'analisis de riesgos')
            || str_contains($normalized, 'plan de')
            || str_contains($normalized, 'sistema ')
            || str_contains($normalized, 'programa medico arquitectonico')
            || str_contains($normalized, 'estructuras existentes');
    }
}
