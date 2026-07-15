<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->timestamp('plane_states_seeded_at')->nullable()->after('plane_last_provisioned_at');
        });

        // Existing Plane projects keep their workflow. Only projects
        // provisioned after this migration receive the Orbit state seed.
        DB::table('projects')
            ->whereNotNull('plane_project_id')
            ->update(['plane_states_seeded_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('plane_states_seeded_at');
        });
    }
};
