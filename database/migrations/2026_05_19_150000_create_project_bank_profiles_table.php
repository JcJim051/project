<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_bank_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();
            $table->unsignedSmallInteger('horizonte_anio_0')->nullable();
            $table->unsignedSmallInteger('horizonte_anio_1')->nullable();
            $table->unsignedSmallInteger('horizonte_anio_2')->nullable();
            $table->unsignedSmallInteger('horizonte_anio_3')->nullable();
            $table->string('tipo_presentacion', 20)->default('proyecto');
            $table->string('tipo_tramite', 20)->default('actualizacion');
            $table->string('codigo_dependencia', 30)->nullable();
            $table->string('dependencia', 255)->nullable();
            $table->unsignedSmallInteger('vigencia')->nullable();
            $table->string('proyecto_titulo_override', 500)->nullable();
            $table->string('pilar', 255)->nullable();
            $table->string('eje', 500)->nullable();
            $table->string('linea', 500)->nullable();
            $table->string('programa', 500)->nullable();
            $table->string('subprograma', 500)->nullable();
            $table->string('meta_plan_codigo', 100)->nullable();
            $table->string('meta_plan_nombre', 500)->nullable();
            $table->string('sector_texto_plantilla', 255)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_bank_profiles');
    }
};
