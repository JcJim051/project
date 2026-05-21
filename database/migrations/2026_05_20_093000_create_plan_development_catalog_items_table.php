<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_development_catalog_items', function (Blueprint $table) {
            $table->id();
            $table->string('pilar_codigo', 30)->nullable();
            $table->string('pilar', 500)->nullable();
            $table->string('eje_codigo', 30)->nullable();
            $table->string('eje', 500)->nullable();
            $table->string('linea_codigo', 30)->nullable();
            $table->string('linea', 500)->nullable();
            $table->string('programa_codigo', 30)->nullable();
            $table->string('programa', 500)->nullable();
            $table->string('subprograma_codigo', 30)->nullable();
            $table->string('subprograma', 500)->nullable();
            $table->string('sector_codigo', 30)->nullable();
            $table->string('sector', 255)->nullable();
            $table->string('codigo_meta_plan', 40)->nullable();
            $table->string('nombre_meta_plan', 1200)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['activo', 'codigo_meta_plan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_development_catalog_items');
    }
};
