<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prioridad_entidad_catalogo', function (Blueprint $table) {
            $table->string('color_hex', 7)->nullable()->after('nombre');
        });

        DB::table('prioridad_entidad_catalogo')->where('numero', 1)->update(['color_hex' => '#DC2626']);
        DB::table('prioridad_entidad_catalogo')->where('numero', 2)->update(['color_hex' => '#F97316']);
        DB::table('prioridad_entidad_catalogo')->where('numero', 3)->update(['color_hex' => '#EAB308']);
        DB::table('prioridad_entidad_catalogo')->where('numero', 4)->update(['color_hex' => '#16A34A']);
    }

    public function down(): void
    {
        Schema::table('prioridad_entidad_catalogo', function (Blueprint $table) {
            $table->dropColumn('color_hex');
        });
    }
};
