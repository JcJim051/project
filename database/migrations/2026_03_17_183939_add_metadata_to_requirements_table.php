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
        Schema::table('requirements', function (Blueprint $table) {
            $table->string('categoria')->nullable()->after('id');
            $table->string('sector')->nullable()->after('categoria');
            $table->string('codigo')->nullable()->after('sector');
            $table->text('descripcion')->nullable()->after('codigo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropColumn(['categoria', 'sector', 'codigo', 'descripcion']);
        });
    }
};
