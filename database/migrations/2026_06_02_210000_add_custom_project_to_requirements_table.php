<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            if (! Schema::hasColumn('requirements', 'custom_project_id')) {
                $table->foreignId('custom_project_id')
                    ->nullable()
                    ->after('parent_id')
                    ->constrained('projects')
                    ->nullOnDelete();
                $table->index(['custom_project_id', 'carpeta']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('requirements', function (Blueprint $table) {
            if (Schema::hasColumn('requirements', 'custom_project_id')) {
                $table->dropIndex(['custom_project_id', 'carpeta']);
                $table->dropConstrainedForeignId('custom_project_id');
            }
        });
    }
};
