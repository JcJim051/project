<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_cycles', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->string('anchor_type', 30)->default('project_created_at');
            $table->integer('start_offset_days')->default(0);
            $table->unsignedInteger('duration_days')->default(14);
            $table->date('fixed_start_date')->nullable();
            $table->date('fixed_end_date')->nullable();
            $table->string('owner_role', 50)->default('estructurador');
            $table->string('timezone', 80)->default('America/Bogota');
            $table->timestamps();
        });

        Schema::create('operational_labels', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('color', 20)->default('#64748B');
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('operational_activity_types', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 80)->unique();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('color', 20)->default('#64748B');
            $table->string('icono')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->boolean('track_as_kpi')->default(true);
            $table->timestamps();
        });

        DB::table('operational_activity_types')->insert([
            ['codigo' => 'requisito', 'nombre' => 'Requisito', 'descripcion' => 'Actividad originada por un requisito de Orbit.', 'color' => '#0F766E', 'orden' => 10, 'activo' => true, 'track_as_kpi' => true, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'actividad_base', 'nombre' => 'Actividad base', 'descripcion' => 'Actividad operativa adicional no asociada a un requisito.', 'color' => '#0369A1', 'orden' => 20, 'activo' => true, 'track_as_kpi' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('operational_cycles')->insert([
            'codigo' => 'corte_inicial',
            'nombre' => 'Corte inicial',
            'descripcion' => 'Plantilla inicial editable. Actívala cuando esté definida la periodicidad operativa.',
            'orden' => 10,
            'activo' => false,
            'anchor_type' => 'project_created_at',
            'start_offset_days' => 0,
            'duration_days' => 14,
            'owner_role' => 'estructurador',
            'timezone' => 'America/Bogota',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $labels = [
            ['certificacion', 'Certificación', '#0F766E'],
            ['diseno', 'Diseño', '#0369A1'],
            ['revision_interna', 'Revisión interna', '#7C3AED'],
            ['tramite_externo', 'Trámite externo', '#B45309'],
            ['critica', 'Crítica', '#B91C1C'],
            ['requiere_visita', 'Requiere visita', '#047857'],
            ['mga', 'MGA', '#1D4ED8'],
            ['documento_tecnico', 'Documento técnico', '#475569'],
        ];
        foreach ($labels as $index => [$codigo, $nombre, $color]) {
            DB::table('operational_labels')->insert([
                'codigo' => $codigo, 'nombre' => $nombre, 'color' => $color,
                'orden' => ($index + 1) * 10, 'activo' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_activity_types');
        Schema::dropIfExists('operational_labels');
        Schema::dropIfExists('operational_cycles');
    }
};
