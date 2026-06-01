<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drive_oauth_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('drive_oauth_settings', 'projects_root_folder_id')) {
                $table->string('projects_root_folder_id', 255)->nullable()->after('redirect_uri');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drive_oauth_settings', function (Blueprint $table) {
            if (Schema::hasColumn('drive_oauth_settings', 'projects_root_folder_id')) {
                $table->dropColumn('projects_root_folder_id');
            }
        });
    }
};
