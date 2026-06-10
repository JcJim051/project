<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuente_financiacions', function (Blueprint $table): void {
            $table->string('tipo', 150)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('fuente_financiacions', function (Blueprint $table): void {
            $table->dropColumn('tipo');
        });
    }
};
