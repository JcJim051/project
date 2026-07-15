<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plane_task_links', function (Blueprint $table) {
            if (! Schema::hasColumn('plane_task_links', 'plane_priority')) {
                $table->string('plane_priority', 20)->nullable()->after('title');
            }
            if (! Schema::hasColumn('plane_task_links', 'responsible_type')) {
                $table->string('responsible_type', 40)->nullable()->after('plane_priority');
            }
            if (! Schema::hasColumn('plane_task_links', 'resolved_user_id')) {
                $table->foreignId('resolved_user_id')->nullable()->after('responsible_type')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('plane_task_links', 'resolved_user_email')) {
                $table->string('resolved_user_email')->nullable()->after('resolved_user_id');
            }
            if (! Schema::hasColumn('plane_task_links', 'resolved_plane_assignee_id')) {
                $table->string('resolved_plane_assignee_id')->nullable()->after('resolved_user_email');
            }
            if (! Schema::hasColumn('plane_task_links', 'assignment_note')) {
                $table->text('assignment_note')->nullable()->after('resolved_plane_assignee_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('plane_task_links', function (Blueprint $table) {
            if (Schema::hasColumn('plane_task_links', 'assignment_note')) {
                $table->dropColumn('assignment_note');
            }
            if (Schema::hasColumn('plane_task_links', 'resolved_plane_assignee_id')) {
                $table->dropColumn('resolved_plane_assignee_id');
            }
            if (Schema::hasColumn('plane_task_links', 'resolved_user_email')) {
                $table->dropColumn('resolved_user_email');
            }
            if (Schema::hasColumn('plane_task_links', 'resolved_user_id')) {
                $table->dropConstrainedForeignId('resolved_user_id');
            }
            if (Schema::hasColumn('plane_task_links', 'responsible_type')) {
                $table->dropColumn('responsible_type');
            }
            if (Schema::hasColumn('plane_task_links', 'plane_priority')) {
                $table->dropColumn('plane_priority');
            }
        });
    }
};
