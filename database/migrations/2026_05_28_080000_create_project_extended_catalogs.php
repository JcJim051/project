<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipio_tipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('municipio_municipio_tipo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('municipio_id')->constrained('municipios')->cascadeOnDelete();
            $table->foreignId('municipio_tipo_id')->constrained('municipio_tipos')->cascadeOnDelete();
            $table->unique(['municipio_id', 'municipio_tipo_id'], 'mun_tipo_unique');
        });

        Schema::create('prioridad_entidad_catalogo', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('numero')->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('profesionales_ambientales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->nullable();
            $table->string('telefono')->nullable();
            $table->string('documento')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('project_stages', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('project_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('manual_allowed')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('execution_years', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('execution_year_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('execution_year_id')->constrained('execution_years')->cascadeOnDelete();
            $table->unique(['project_id', 'execution_year_id'], 'project_year_unique');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('prioridad_entidad_id')->nullable()->after('estructurador_id')->constrained('prioridad_entidad_catalogo')->nullOnDelete();
            $table->unsignedInteger('prioridad_estructurador')->nullable()->after('prioridad_entidad_id');
            $table->foreignId('profesional_ambiental_id')->nullable()->after('prioridad_estructurador')->constrained('profesionales_ambientales')->nullOnDelete();
            $table->foreignId('project_stage_id')->nullable()->after('profesional_ambiental_id')->constrained('project_stages')->nullOnDelete();
            $table->foreignId('project_status_id')->nullable()->after('project_stage_id')->constrained('project_statuses')->nullOnDelete();
            $table->unsignedInteger('duracion_meses')->nullable()->after('project_status_id');
            $table->unsignedBigInteger('poblacion_objetivo')->nullable()->after('duracion_meses');
            $table->unique(['estructurador_id', 'prioridad_estructurador'], 'project_priority_by_estructurador_unique');
        });

        DB::table('prioridad_entidad_catalogo')->insert([
            ['numero' => 1, 'nombre' => 'Crítica', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['numero' => 2, 'nombre' => 'Alta', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['numero' => 3, 'nombre' => 'Media', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['numero' => 4, 'nombre' => 'Baja', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('project_stages')->insert([
            ['nombre' => 'Preinversión', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Inversión', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Operación', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('project_statuses')->insert([
            ['nombre' => 'Iniciativa', 'orden' => 1, 'manual_allowed' => false, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Formulación y presentación', 'orden' => 2, 'manual_allowed' => false, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Viabilidad y registro', 'orden' => 3, 'manual_allowed' => false, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Priorización y aprobación', 'orden' => 4, 'manual_allowed' => false, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Ejecución', 'orden' => 5, 'manual_allowed' => true, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Archivado', 'orden' => 6, 'manual_allowed' => true, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Ajuste', 'orden' => 7, 'manual_allowed' => true, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'Ajustado en ejecución', 'orden' => 8, 'manual_allowed' => true, 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $currentYear = (int) now()->year;
        $years = [];
        for ($year = $currentYear; $year <= $currentYear + 10; $year++) {
            $years[] = ['anio' => $year, 'activo' => true, 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('execution_years')->insert($years);

        DB::table('municipio_tipos')->insert([
            ['nombre' => 'PEDER', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
            ['nombre' => 'SOMAC', 'activo' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $defaultStageId = DB::table('project_stages')->where('nombre', 'Preinversión')->value('id');
        $initiativeStatusId = DB::table('project_statuses')->where('nombre', 'Iniciativa')->value('id');
        if ($defaultStageId) {
            DB::table('projects')->whereNull('project_stage_id')->update(['project_stage_id' => $defaultStageId]);
        }
        if ($initiativeStatusId) {
            DB::table('projects')->whereNull('project_status_id')->update(['project_status_id' => $initiativeStatusId]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique('project_priority_by_estructurador_unique');
            $table->dropConstrainedForeignId('project_status_id');
            $table->dropConstrainedForeignId('project_stage_id');
            $table->dropConstrainedForeignId('profesional_ambiental_id');
            $table->dropColumn('prioridad_estructurador');
            $table->dropConstrainedForeignId('prioridad_entidad_id');
            $table->dropColumn('duracion_meses');
            $table->dropColumn('poblacion_objetivo');
        });

        Schema::dropIfExists('execution_year_project');
        Schema::dropIfExists('execution_years');
        Schema::dropIfExists('project_statuses');
        Schema::dropIfExists('project_stages');
        Schema::dropIfExists('profesionales_ambientales');
        Schema::dropIfExists('prioridad_entidad_catalogo');
        Schema::dropIfExists('municipio_municipio_tipo');
        Schema::dropIfExists('municipio_tipos');
    }
};
