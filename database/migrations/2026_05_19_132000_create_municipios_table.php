<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('municipio_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'municipio_id']);
        });

        $municipios = [
            'Acacías',
            'Barranca de Upía',
            'Cabuyaro',
            'Castilla La Nueva',
            'Cubarral',
            'Cumaral',
            'El Calvario',
            'El Castillo',
            'El Dorado',
            'Fuente de Oro',
            'Granada',
            'Guamal',
            'La Macarena',
            'Lejanías',
            'Mapiripán',
            'Mesetas',
            'Puerto Concordia',
            'Puerto Gaitán',
            'Puerto Lleras',
            'Puerto López',
            'Puerto Rico',
            'Restrepo',
            'San Carlos de Guaroa',
            'San Juan de Arama',
            'San Juanito',
            'San Martín',
            'Uribe',
            'Villavicencio',
            'Vista Hermosa',
        ];

        foreach ($municipios as $nombre) {
            DB::table('municipios')->updateOrInsert(
                ['nombre' => $nombre],
                ['activo' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('municipio_project');
        Schema::dropIfExists('municipios');
    }
};
