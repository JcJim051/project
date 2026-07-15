<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('plane_project_id')->nullable()->after('drive_folder_id');
            $table->string('plane_project_url')->nullable()->after('plane_project_id');
            $table->string('plane_sync_status', 30)->nullable()->after('plane_project_url');
            $table->timestamp('plane_last_provisioned_at')->nullable()->after('plane_sync_status');
            $table->text('plane_last_error')->nullable()->after('plane_last_provisioned_at');
            $table->foreignId('plane_connection_id')->nullable()->after('plane_last_error')->constrained('plane_connections')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plane_connection_id');
            $table->dropColumn([
                'plane_project_id',
                'plane_project_url',
                'plane_sync_status',
                'plane_last_provisioned_at',
                'plane_last_error',
            ]);
        });
    }
};
