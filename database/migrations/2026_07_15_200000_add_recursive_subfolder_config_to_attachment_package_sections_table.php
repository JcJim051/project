<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attachment_package_sections', function (Blueprint $table): void {
            $table->string('recursive_root_folder')->nullable()->after('source_folder');
            $table->json('recursive_source_folders')->nullable()->after('include_all_folder_files');
        });

        DB::table('attachment_package_sections')
            ->where('name', '2 Presupuesto CT')
            ->update([
                'recursive_root_folder' => '02 Presupuesto',
                'recursive_source_folders' => json_encode([
                    '2.1 Presupuesto',
                    '2.4 Estudio de Mercado',
                    '2.6 Programación',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        DB::table('attachment_package_sections')
            ->where('name', '1 Formulacion 2 Documentos del Banco CT')
            ->update([
                'recursive_root_folder' => '01 Formulacion',
                'recursive_source_folders' => json_encode([
                    'Documentos del Banco',
                ], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('attachment_package_sections')
            ->where('name', '2 Presupuesto CT')
            ->update([
                'recursive_root_folder' => null,
                'recursive_source_folders' => null,
                'updated_at' => now(),
            ]);

        DB::table('attachment_package_sections')
            ->where('name', '1 Formulacion 2 Documentos del Banco CT')
            ->update([
                'recursive_root_folder' => null,
                'recursive_source_folders' => null,
                'updated_at' => now(),
            ]);

        Schema::table('attachment_package_sections', function (Blueprint $table): void {
            $table->dropColumn(['recursive_root_folder', 'recursive_source_folders']);
        });
    }
};
