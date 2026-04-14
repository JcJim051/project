<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->string('numeracion')->nullable()->after('id');
            $table->text('requisito')->nullable()->after('numeracion');
            $table->string('nombre_documento')->nullable()->after('requisito');
            $table->string('carpeta')->nullable()->after('nombre_documento');
        });

        // Drop legacy columns using raw SQL to avoid doctrine/dbal dependency.
        DB::statement('ALTER TABLE requirements 
            DROP COLUMN categoria,
            DROP COLUMN sector,
            DROP COLUMN codigo,
            DROP COLUMN descripcion,
            DROP COLUMN nivel1,
            DROP COLUMN nivel2,
            DROP COLUMN nivel3,
            DROP COLUMN archivo,
            DROP COLUMN extension,
            DROP COLUMN aplica,
            DROP COLUMN coordinador
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->string('nivel1')->nullable();
            $table->string('nivel2')->nullable();
            $table->string('nivel3')->nullable();
            $table->string('archivo')->nullable();
            $table->string('extension')->nullable();
            $table->boolean('aplica')->default(true);
            $table->string('coordinador')->nullable();
            $table->string('categoria')->nullable();
            $table->string('sector')->nullable();
            $table->string('codigo')->nullable();
            $table->text('descripcion')->nullable();
        });

        DB::statement('ALTER TABLE requirements 
            DROP COLUMN numeracion,
            DROP COLUMN requisito,
            DROP COLUMN nombre_documento,
            DROP COLUMN carpeta
        ');
    }
};
