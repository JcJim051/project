<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_states', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 50)->unique();
            $table->string('nombre');
            $table->unsignedInteger('orden')->default(0);
            $table->string('color', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('es_final')->default(false);
            $table->boolean('es_bloqueante')->default(false);
            $table->string('equivalente_plane')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('operational_states')->insert([
            ['codigo' => 'pendiente', 'nombre' => 'Pendiente', 'orden' => 1, 'color' => '#9CA3AF', 'activo' => true, 'es_final' => false, 'es_bloqueante' => false, 'equivalente_plane' => 'pending', 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'en_ejecucion', 'nombre' => 'En ejecución', 'orden' => 2, 'color' => '#3B82F6', 'activo' => true, 'es_final' => false, 'es_bloqueante' => false, 'equivalente_plane' => 'in_progress', 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'en_revision', 'nombre' => 'En revisión', 'orden' => 3, 'color' => '#F59E0B', 'activo' => true, 'es_final' => false, 'es_bloqueante' => false, 'equivalente_plane' => 'in_review', 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'ajustes', 'nombre' => 'Ajustes', 'orden' => 4, 'color' => '#F97316', 'activo' => true, 'es_final' => false, 'es_bloqueante' => false, 'equivalente_plane' => 'changes_requested', 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'completado', 'nombre' => 'Completado', 'orden' => 5, 'color' => '#16A34A', 'activo' => true, 'es_final' => true, 'es_bloqueante' => false, 'equivalente_plane' => 'completed', 'created_at' => $now, 'updated_at' => $now],
            ['codigo' => 'bloqueado', 'nombre' => 'Bloqueado', 'orden' => 6, 'color' => '#DC2626', 'activo' => true, 'es_final' => false, 'es_bloqueante' => true, 'equivalente_plane' => 'blocked', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_states');
    }
};
