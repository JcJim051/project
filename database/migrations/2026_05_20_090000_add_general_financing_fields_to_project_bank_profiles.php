<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_bank_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('project_bank_profiles', 'codigo_fuente')) {
                $table->string('codigo_fuente', 80)->nullable()->after('subprograma');
            }
            if (! Schema::hasColumn('project_bank_profiles', 'nombre_fuente')) {
                $table->string('nombre_fuente', 255)->nullable()->after('codigo_fuente');
            }
            if (! Schema::hasColumn('project_bank_profiles', 'municipio_relacion')) {
                $table->string('municipio_relacion', 255)->nullable()->after('meta_plan_nombre');
            }
            if (! Schema::hasColumn('project_bank_profiles', 'beneficiarios')) {
                $table->unsignedInteger('beneficiarios')->nullable()->after('municipio_relacion');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_bank_profiles', function (Blueprint $table) {
            foreach (['beneficiarios', 'municipio_relacion', 'nombre_fuente', 'codigo_fuente'] as $column) {
                if (Schema::hasColumn('project_bank_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
