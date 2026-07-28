<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $now = now();
            $requirementName = 'Revisión de licencias y permisos';

            $requirement = DB::table('requirements')
                ->where('origen', 'workflow_post_structure')
                ->where('carpeta', 'Precontractual')
                ->where('nombre_documento', $requirementName)
                ->first();

            $requirementData = [
                'codigo_interno' => 'WF-PRE-LIC',
                'texto' => $requirementName,
                'tipo' => 'Flujo posterior',
                'requiere_check' => 'SI',
                'orden' => '1',
                'requisito' => $requirementName,
                'nombre_documento' => $requirementName,
                'carpeta' => 'Precontractual',
                'evidence_format_rule' => 'cualquiera',
                'origen' => 'workflow_post_structure',
                'visible' => true,
                'updated_at' => $now,
            ];

            if ($requirement) {
                DB::table('requirements')
                    ->where('id', $requirement->id)
                    ->update($requirementData);
                $requirementId = (int) $requirement->id;
            } else {
                $requirementId = (int) DB::table('requirements')->insertGetId([
                    ...$requirementData,
                    'created_at' => $now,
                ]);
            }

            foreach (['sgr' => 7, 'propios' => 6] as $fundingSource => $sortOrder) {
                $stage = DB::table('project_workflow_stages')
                    ->where('funding_source', $fundingSource)
                    ->where(function ($query): void {
                        $query
                            ->where('name', 'Precontractual')
                            ->orWhereIn('slug', ['precontractual', 'Verificacion-de-requisitos']);
                    })
                    ->first();

                $stageData = [
                    'funding_source' => $fundingSource,
                    'name' => 'Precontractual',
                    'sort_order' => $sortOrder,
                    'is_optional' => false,
                    'optional_rule' => null,
                    'is_active' => true,
                    'updated_at' => $now,
                ];

                if ($stage) {
                    DB::table('project_workflow_stages')
                        ->where('id', $stage->id)
                        ->update($stageData);
                    $stageId = (int) $stage->id;
                } else {
                    $stageId = (int) DB::table('project_workflow_stages')->insertGetId([
                        ...$stageData,
                        'slug' => 'precontractual',
                        'created_at' => $now,
                    ]);
                }

                $step = DB::table('project_workflow_steps')
                    ->where('stage_id', $stageId)
                    ->where(function ($query) use ($requirementName): void {
                        $query
                            ->where('name', $requirementName)
                            ->orWhere('slug', 'revision-de-licencias-y-permisos');
                    })
                    ->first();

                $stepData = [
                    'stage_id' => $stageId,
                    'name' => $requirementName,
                    'description' => 'Seguimiento de licencias y permisos definitivos requeridos antes de la contratación.',
                    'completion_rule' => 'license_permit_definitives',
                    'sort_order' => 1,
                    'is_active' => true,
                    'updated_at' => $now,
                ];

                if ($step) {
                    DB::table('project_workflow_steps')
                        ->where('id', $step->id)
                        ->update($stepData);
                    $stepId = (int) $step->id;
                } else {
                    $stepId = (int) DB::table('project_workflow_steps')->insertGetId([
                        ...$stepData,
                        'slug' => 'revision-de-licencias-y-permisos',
                        'created_at' => $now,
                    ]);
                }

                DB::table('project_workflow_step_requirements')->updateOrInsert(
                    [
                        'step_id' => $stepId,
                        'requirement_id' => $requirementId,
                    ],
                    [
                        'is_required' => true,
                        'sort_order' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }
        });
    }

    public function down(): void
    {
        // Data is intentionally preserved to avoid deleting historical workflow states.
    }
};
