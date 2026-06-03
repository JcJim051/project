<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_package_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('attachment_package_sections')->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('active')->default(true);
            $table->string('source_group_code', 5)->nullable();
            $table->string('source_folder')->nullable();
            $table->string('match_type', 40)->default('folder');
            $table->json('code_prefixes')->nullable();
            $table->json('allowed_extensions')->nullable();
            $table->boolean('include_all_folder_files')->default(false);
            $table->timestamps();

            $table->index(['parent_id', 'orden']);
            $table->index(['active', 'source_group_code']);
        });

        $now = now();
        $groups = [
            ['key' => '01', 'name' => '01 Formulacion', 'orden' => 10],
            ['key' => '02', 'name' => '02 Presupuesto', 'orden' => 20],
            ['key' => '03', 'name' => '03 Certificaciones', 'orden' => 30],
            ['key' => '04', 'name' => '04 Licencias y Permisos', 'orden' => 40],
            ['key' => '05', 'name' => '05 Estudios y Disenos', 'orden' => 50],
        ];

        $groupIds = [];
        foreach ($groups as $group) {
            $id = DB::table('attachment_package_sections')->insertGetId([
                'name' => $group['name'],
                'orden' => $group['orden'],
                'active' => true,
                'source_group_code' => $group['key'],
                'match_type' => 'group',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $groupIds[$group['key']] = $id;
        }

        $sections = [
            ['parent' => '01', 'name' => '1 Formulacion 1 Requisitos Generales CT', 'orden' => 10, 'match_type' => 'code_prefix', 'code_prefixes' => ['1.01', '1.02', '1.03', '1.04', '1.05']],
            ['parent' => '01', 'name' => '1 Formulacion 2 Documentos del Banco CT', 'orden' => 20, 'match_type' => 'code_prefix', 'code_prefixes' => ['1.06']],
            ['parent' => '01', 'name' => '1 Formulacion 3 Otros Soportes CT', 'orden' => 30, 'match_type' => 'code_prefix', 'code_prefixes' => ['1.07', '1.08', '1.09', '1.1', '1.11', '1.12', '1.13']],
            ['parent' => '02', 'name' => '2 Presupuesto CT', 'orden' => 10, 'match_type' => 'group_code', 'source_group_code' => '02'],
            ['parent' => '03', 'name' => '03 Certificaciones 1 Certificaciones Generales', 'orden' => 10, 'match_type' => 'folder', 'source_folder' => '3.1 Certificaciones Generales'],
            ['parent' => '03', 'name' => '03 Certificaciones 2 Certificaciones Generales Adicionales', 'orden' => 20, 'match_type' => 'folder', 'source_folder' => '3.2 Certificaciones Generales Adicionales'],
            ['parent' => '03', 'name' => '03 Certificaciones 3 Otras Certificaciones', 'orden' => 30, 'match_type' => 'folder', 'source_folder' => '3.3 Otras Certificaciones', 'include_all_folder_files' => true, 'allowed_extensions' => ['pdf']],
            ['parent' => '03', 'name' => '03 Certificaciones 4 Documentos Sectoriales', 'orden' => 40, 'match_type' => 'folder', 'source_folder' => '3.4 Documentos Sectoriales'],
            ['parent' => '04', 'name' => '4 Licencias y Permisos CT', 'orden' => 10, 'match_type' => 'folder', 'source_folder' => '04 Licencias y Permisos', 'include_all_folder_files' => true, 'allowed_extensions' => ['pdf']],
            ['parent' => '05', 'name' => '05 Estudios y Disenos', 'orden' => 10, 'match_type' => 'studies_subfolders', 'source_group_code' => '05'],
        ];

        foreach ($sections as $section) {
            DB::table('attachment_package_sections')->insert([
                'parent_id' => $groupIds[$section['parent']],
                'name' => $section['name'],
                'orden' => $section['orden'],
                'active' => true,
                'source_group_code' => $section['source_group_code'] ?? $section['parent'],
                'source_folder' => $section['source_folder'] ?? null,
                'match_type' => $section['match_type'],
                'code_prefixes' => isset($section['code_prefixes']) ? json_encode($section['code_prefixes'], JSON_UNESCAPED_UNICODE) : null,
                'allowed_extensions' => isset($section['allowed_extensions']) ? json_encode($section['allowed_extensions'], JSON_UNESCAPED_UNICODE) : null,
                'include_all_folder_files' => (bool) ($section['include_all_folder_files'] ?? false),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_package_sections');
    }
};
