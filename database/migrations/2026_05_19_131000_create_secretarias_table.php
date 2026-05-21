<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretarias', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $secretarias = [
            'Agencia para la Infraestructura del Meta - AIM',
            'Casa de la Cultura Jorge Eliecer Gaitán',
            'Departamento Administrativo de Planeación',
            'Departamento Administrativo de Planeación - Unillanos',
            'Dirección Departamental para la Gestión del Riesgo de Desastres',
            'Dirección para el Fomento de la Educación Superior',
            'Empresa de Servicios Públicos del Departamento del Meta - EDESA S.A. Esp',
            'Instituto de Cultura del Meta',
            'Instituto de Deporte y Recreación del Meta',
            'Instituto de Turismo',
            'Instituto Departamental de Tránsito y Transporte',
            'Oficina de Control Interno',
            'Secretaria Administrativa',
            'Secretaria de Agricultura y Desarrollo Rural',
            'Secretaria de Ambiente',
            'Secretaria de Competitividad y Desarrollo Económico',
            'Secretaria de Comunicaciones',
            'Secretaria de Derechos Humanos y Paz',
            'Secretaría de Educación',
            'Secretaría de Gobierno y Seguridad',
            'Secretaria de Hacienda',
            'Secretaría de la Mujer, la Familia y la Equidad de Género',
            'Secretaria de Minas y Energía',
            'Secretaría de Salud del Meta',
            'Secretaria de Vivienda',
            'Secretaria Jurídica',
            'Secretaria Social',
            'Secretaria Tics',
            'Municipio',
            'Otro',
        ];

        foreach ($secretarias as $nombre) {
            DB::table('secretarias')->updateOrInsert(
                ['nombre' => $nombre],
                ['activo' => true, 'updated_at' => now(), 'created_at' => now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('secretarias');
    }
};
