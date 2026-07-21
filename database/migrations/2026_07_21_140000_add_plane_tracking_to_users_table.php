<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'plane_user_id')) {
                $table->string('plane_user_id')->nullable()->after('must_change_password');
            }

            if (! Schema::hasColumn('users', 'plane_sync_status')) {
                $table->string('plane_sync_status', 30)->nullable()->after('plane_user_id');
            }

            if (! Schema::hasColumn('users', 'plane_last_synced_at')) {
                $table->timestamp('plane_last_synced_at')->nullable()->after('plane_sync_status');
            }

            if (! Schema::hasColumn('users', 'plane_last_error')) {
                $table->text('plane_last_error')->nullable()->after('plane_last_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (['plane_last_error', 'plane_last_synced_at', 'plane_sync_status', 'plane_user_id'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
