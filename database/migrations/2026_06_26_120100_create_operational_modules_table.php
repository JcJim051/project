<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_modules', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->unsignedInteger('orden')->default(0);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('crea_tareas')->default(false);
            $table->string('color', 20)->nullable();
            $table->string('icono', 100)->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('operational_modules')->insert([
            ['codigo' => '01', 'nombre' => 'Puesta a punto', 'orden' => 1, 'activo' => true, 'crea_tareas' => false, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => '02', 'nombre' => 'Estudios y Diseños', 'orden' => 2, 'activo' => true, 'crea_tareas' => false, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => '03', 'nombre' => 'Presupuesto', 'orden' => 3, 'activo' => true, 'crea_tareas' => false, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => '04', 'nombre' => 'Formulación MGA', 'orden' => 4, 'activo' => true, 'crea_tareas' => false, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => '05', 'nombre' => 'Documento Técnico', 'orden' => 5, 'activo' => true, 'crea_tareas' => false, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => '06', 'nombre' => 'Certificaciones', 'orden' => 6, 'activo' => true, 'crea_tareas' => false, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => '07', 'nombre' => 'Radicación MGA', 'orden' => 7, 'activo' => true, 'crea_tareas' => false, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => '08', 'nombre' => 'Revisión Interna AIM', 'orden' => 8, 'activo' => true, 'crea_tareas' => false, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => '09', 'nombre' => 'Observaciones', 'orden' => 9, 'activo' => true, 'crea_tareas' => false, 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => '10', 'nombre' => 'Viabilidad', 'orden' => 10, 'activo' => true, 'crea_tareas' => false, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_modules');
    }
};
