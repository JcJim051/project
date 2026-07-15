<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $typeId = DB::table('operational_activity_types')->where('codigo', 'actividad_base')->value('id');
        $activities = [
            ['01', 'Definir objeto', 'Definir el objeto del proyecto y dejarlo listo para seguimiento operativo.', 5, false, 'high', 'estructurador'],
            ['01', 'Seleccionar meta y producto MGA', 'Seleccionar meta y producto MGA y dejarlo listo para seguimiento operativo.', 10, false, 'high', 'formulador'],
            ['02', 'Visita técnica', 'Realizar la visita técnica del estudio y dejar soporte listo para validación.', 10, true, 'medium', 'especialista_estudio'],
            ['02', 'Coordinar especialista', 'Coordinar la ejecución del estudio con el especialista responsable.', 20, true, 'medium', 'estructurador'],
            ['02', 'Revisión interna del estudio', 'Revisar internamente el estudio antes de darlo por consolidado.', 30, true, 'medium', 'especialista_estudio'],
            ['02', 'Cierre técnico del estudio', 'Cerrar técnicamente el estudio y dejarlo listo para validación.', 40, true, 'medium', 'especialista_estudio'],
            ['08', 'Consolidar expediente para radicación', 'Consolidar el expediente y dejarlo listo para radicación en MGA.', 10, false, 'medium', 'estructurador'],
            ['08', 'Cargar expediente en MGA', 'Cargar el expediente en MGA y dejar evidencia del trámite.', 20, false, 'medium', 'formulador'],
            ['08', 'Registrar radicado', 'Registrar el radicado del proyecto y dejar soporte listo para consulta.', 30, false, 'medium', 'formulador'],
            ['08', 'Confirmar envío', 'Confirmar el envío del proyecto y dejar constancia operativa.', 40, false, 'medium', 'formulador'],
            ['09', 'Consolidar revisión interna', 'Consolidar la revisión interna y dejar soporte listo para validación.', 10, false, 'medium', 'estructurador'],
            ['09', 'Emitir observaciones internas', 'Emitir observaciones internas y dejarlas listas para seguimiento.', 20, false, 'medium', 'estructurador'],
            ['09', 'Cerrar aval interno', 'Cerrar el aval interno y dejar soporte listo para radicación.', 30, false, 'medium', 'estructurador'],
            ['10', 'Registrar observaciones recibidas', 'Registrar las observaciones recibidas y dejarlas listas para seguimiento.', 10, false, 'medium', 'estructurador'],
            ['10', 'Subsanar observaciones', 'Subsanar observaciones y dejar soporte listo para validación.', 20, false, 'medium', 'formulador'],
            ['10', 'Cerrar ciclo de observaciones', 'Cerrar el ciclo de observaciones y dejar evidencia del resultado.', 30, false, 'medium', 'estructurador'],
            ['11', 'Hacer seguimiento a viabilidad', 'Hacer seguimiento a la viabilidad y dejar soporte listo para consulta.', 10, false, 'medium', 'estructurador'],
            ['11', 'Registrar resultado de viabilidad', 'Registrar el resultado de viabilidad y dejar soporte listo para validación.', 20, false, 'medium', 'estructurador'],
            ['11', 'Cerrar frente de viabilidad', 'Cerrar el frente de viabilidad y dejar evidencia del resultado.', 30, false, 'medium', 'estructurador'],
        ];

        foreach ($activities as [$moduleCode, $title, $description, $order, $perStudy, $priority, $responsible]) {
            $moduleId = DB::table('operational_modules')->where('codigo', $moduleCode)->value('id');
            if (! $moduleId) {
                continue;
            }

            DB::table('operational_activity_mappings')->updateOrInsert(
                ['operational_module_id' => $moduleId, 'source_type' => 'generic', 'titulo_operativo' => $title],
                [
                    'operational_activity_type_id' => $typeId,
                    'requirement_id' => null,
                    'source_origin' => 'catalog',
                    'repeat_per_study' => $perStudy,
                    'descripcion_operativa' => $description,
                    'plane_priority' => $priority,
                    'responsible_type' => $responsible,
                    'planned_start_rule' => 'none',
                    'start_offset_days' => 0,
                    'track_as_kpi' => true,
                    'orden' => $order,
                    'activo' => true,
                    'create_automatically' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('operational_activity_mappings')->where('source_type', 'generic')->where('source_origin', 'catalog')->delete();
    }
};
