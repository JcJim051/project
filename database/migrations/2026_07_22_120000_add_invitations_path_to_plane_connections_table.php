<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plane_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('plane_connections', 'invitations_path')) {
                $table->string('invitations_path')
                    ->default('/api/v1/workspaces/{workspace_slug}/invitations/')
                    ->after('issues_path_template');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plane_connections', function (Blueprint $table) {
            if (Schema::hasColumn('plane_connections', 'invitations_path')) {
                $table->dropColumn('invitations_path');
            }
        });
    }
};
