<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->string('codigo_norma')->nullable()->after('id');
            $table->string('codigo_interno')->nullable()->after('codigo_norma');
            $table->unsignedBigInteger('parent_id')->nullable()->after('codigo_interno');
            $table->text('texto')->nullable()->after('parent_id');
            $table->string('sector')->nullable()->after('texto');
            $table->string('tipo')->nullable()->after('sector');
            $table->string('requiere_check')->nullable()->after('tipo');
            $table->string('orden')->nullable()->after('requiere_check');
            $table->string('literal')->nullable()->after('orden');
            $table->string('origen')->nullable()->after('carpeta');

            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            $table->dropIndex(['parent_id']);
            $table->dropColumn([
                'codigo_norma',
                'codigo_interno',
                'parent_id',
                'texto',
                'sector',
                'tipo',
                'requiere_check',
                'orden',
                'literal',
                'origen',
            ]);
        });
    }
};
