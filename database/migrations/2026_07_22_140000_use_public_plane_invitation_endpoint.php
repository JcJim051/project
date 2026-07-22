<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('plane_connections', 'invitations_path')) {
            return;
        }

        DB::table('plane_connections')
            ->where('invitations_path', '/api/workspaces/{workspace_slug}/invitations/')
            ->update([
                'invitations_path' => '/api/v1/workspaces/{workspace_slug}/invitations/',
            ]);
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed.
    }
};
