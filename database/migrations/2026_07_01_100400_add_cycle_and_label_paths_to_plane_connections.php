<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plane_connections', function (Blueprint $table) {
            $table->string('labels_path_template')->default('/api/v1/workspaces/{workspace_slug}/projects/{project_id}/labels/')->after('states_path_template');
            $table->string('cycles_path_template')->default('/api/v1/workspaces/{workspace_slug}/projects/{project_id}/cycles/')->after('labels_path_template');
            $table->string('cycle_issues_path_template')->default('/api/v1/workspaces/{workspace_slug}/projects/{project_id}/cycles/{cycle_id}/cycle-issues/')->after('cycles_path_template');
        });
    }

    public function down(): void
    {
        Schema::table('plane_connections', fn (Blueprint $table) => $table->dropColumn([
            'labels_path_template', 'cycles_path_template', 'cycle_issues_path_template',
        ]));
    }
};
