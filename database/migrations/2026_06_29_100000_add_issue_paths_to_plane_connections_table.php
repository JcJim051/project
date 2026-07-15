<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plane_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('plane_connections', 'issues_path_template')) {
                $table->string('issues_path_template')
                    ->default('/api/v1/workspaces/{workspace_slug}/projects/{project_id}/issues/')
                    ->after('states_path_template');
            }

            if (! Schema::hasColumn('plane_connections', 'issue_detail_path_template')) {
                $table->string('issue_detail_path_template')
                    ->default('/api/v1/workspaces/{workspace_slug}/projects/{project_id}/issues/{issue_id}/')
                    ->after('issues_path_template');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plane_connections', function (Blueprint $table) {
            if (Schema::hasColumn('plane_connections', 'issue_detail_path_template')) {
                $table->dropColumn('issue_detail_path_template');
            }

            if (Schema::hasColumn('plane_connections', 'issues_path_template')) {
                $table->dropColumn('issues_path_template');
            }
        });
    }
};
