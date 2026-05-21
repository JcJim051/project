<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_bank_financing_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->string('producto_mga', 255)->nullable();
            $table->string('actividad', 500)->nullable();
            $table->decimal('valor_actividad', 16, 2)->nullable();
            $table->string('codigo_fuente', 80)->nullable();
            $table->string('nombre_fuente', 255)->nullable();
            $table->string('meta_plan_codigo', 100)->nullable();
            $table->string('meta_plan_nombre', 500)->nullable();
            $table->string('municipio_relacion', 255)->nullable();
            $table->unsignedInteger('beneficiarios')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_bank_financing_rows');
    }
};
