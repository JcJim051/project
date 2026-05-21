<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            if (! Schema::hasColumn('sectors', 'codigo')) {
                $table->string('codigo', 10)->nullable()->after('id');
            }

            if (! Schema::hasColumn('sectors', 'activo')) {
                $table->boolean('activo')->default(true)->after('nombre');
            }
        });

        $sectors = [
            ['codigo' => '04', 'nombre' => 'Información Estadística'],
            ['codigo' => '17', 'nombre' => 'Agricultura y desarrollo rural'],
            ['codigo' => '19', 'nombre' => 'Salud y protección social'],
            ['codigo' => '21', 'nombre' => 'Minas y energía'],
            ['codigo' => '22', 'nombre' => 'Educación'],
            ['codigo' => '23', 'nombre' => 'Tecnologías de la información y las comunicaciones'],
            ['codigo' => '24', 'nombre' => 'Transporte'],
            ['codigo' => '32', 'nombre' => 'Ambiente y desarrollo sostenible'],
            ['codigo' => '33', 'nombre' => 'Cultura'],
            ['codigo' => '35', 'nombre' => 'Comercio, industria y turismo'],
            ['codigo' => '36', 'nombre' => 'Trabajo'],
            ['codigo' => '39', 'nombre' => 'Ciencia, tecnología e innovación'],
            ['codigo' => '40', 'nombre' => 'Vivienda, ciudad y territorio'],
            ['codigo' => '41', 'nombre' => 'Inclusión social y reconciliación'],
            ['codigo' => '43', 'nombre' => 'Deporte y Recreación'],
            ['codigo' => '45', 'nombre' => 'Gobierno Territorial'],
        ];

        $activeNames = collect($sectors)->pluck('nombre')->all();
        DB::table('sectors')
            ->whereNotIn('nombre', $activeNames)
            ->update(['activo' => false, 'updated_at' => now()]);

        foreach ($sectors as $sector) {
            DB::table('sectors')->updateOrInsert(
                ['nombre' => $sector['nombre']],
                [
                    'codigo' => $sector['codigo'],
                    'activo' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            if (Schema::hasColumn('sectors', 'activo')) {
                $table->dropColumn('activo');
            }

            if (Schema::hasColumn('sectors', 'codigo')) {
                $table->dropColumn('codigo');
            }
        });
    }
};
