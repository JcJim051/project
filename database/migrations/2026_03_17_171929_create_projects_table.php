<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('objeto_proyecto');
            $table->string('id_proyecto')->unique();
            $table->string('nombre_clave')->nullable();
            $table->string('municipio');
            $table->string('secretaria')->nullable();
            $table->string('ruta_drive')->nullable();
            $table->foreignId('formulador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('estructurador_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha_creacion')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
