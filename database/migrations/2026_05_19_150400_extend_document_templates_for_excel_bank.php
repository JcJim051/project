<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('document_templates', 'template_type')) {
                $table->string('template_type', 60)->nullable()->after('nombre');
            }
            if (! Schema::hasColumn('document_templates', 'file_kind')) {
                $table->string('file_kind', 20)->default('docx')->after('template_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $table) {
            if (Schema::hasColumn('document_templates', 'file_kind')) {
                $table->dropColumn('file_kind');
            }
            if (Schema::hasColumn('document_templates', 'template_type')) {
                $table->dropColumn('template_type');
            }
        });
    }
};
