<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'funding_source')) {
                $table->string('funding_source', 20)->default('sgr')->after('bipin');
            }
        });

        Schema::table('project_sector', function (Blueprint $table) {
            if (!Schema::hasColumn('project_sector', 'is_primary')) {
                $table->boolean('is_primary')->default(false)->after('sector_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_sector', function (Blueprint $table) {
            if (Schema::hasColumn('project_sector', 'is_primary')) {
                $table->dropColumn('is_primary');
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'funding_source')) {
                $table->dropColumn('funding_source');
            }
        });
    }
};
