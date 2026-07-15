<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('operational_modules')) {
            return;
        }

        $now = now();

        $byCode = DB::table('operational_modules')->pluck('id', 'codigo');

        if ($byCode->has('06')) {
            DB::table('operational_modules')->where('codigo', '06')->update([
                'nombre' => 'Licencias y Permisos',
                'orden' => 6,
                'updated_at' => $now,
            ]);
        }

        if ($byCode->has('07')) {
            DB::table('operational_modules')->where('codigo', '07')->update([
                'nombre' => 'Certificaciones',
                'orden' => 7,
                'updated_at' => $now,
            ]);
        }

        if ($byCode->has('08')) {
            DB::table('operational_modules')->where('codigo', '08')->update([
                'nombre' => 'Radicación MGA',
                'orden' => 8,
                'updated_at' => $now,
            ]);
        }

        if ($byCode->has('09')) {
            DB::table('operational_modules')->where('codigo', '09')->update([
                'nombre' => 'Revisión Interna AIM',
                'orden' => 9,
                'updated_at' => $now,
            ]);
        }

        if ($byCode->has('10')) {
            DB::table('operational_modules')->where('codigo', '10')->update([
                'nombre' => 'Observaciones',
                'orden' => 10,
                'updated_at' => $now,
            ]);
        }

        if (! $byCode->has('11')) {
            DB::table('operational_modules')->insert([
                'codigo' => '11',
                'nombre' => 'Viabilidad',
                'orden' => 11,
                'descripcion' => 'Seguimiento al resultado de viabilidad.',
                'activo' => true,
                'crea_tareas' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // No reversible safely because rows may have been edited from the front.
    }
};
