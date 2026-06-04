<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_transfer_requests', function (Blueprint $table): void {
            if (!Schema::hasColumn('project_transfer_requests', 'director_status')) {
                $table->string('director_status', 20)->default('pending')->after('status');
                $table->text('director_note')->nullable()->after('director_status');
                $table->timestamp('director_decided_at')->nullable()->after('director_note');
                $table->foreignId('director_decided_by_user_id')->nullable()->after('director_decided_at')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('project_transfer_requests', 'planning_status')) {
                $table->string('planning_status', 20)->default('pending')->after('director_decided_by_user_id');
                $table->text('planning_note')->nullable()->after('planning_status');
                $table->timestamp('planning_decided_at')->nullable()->after('planning_note');
                $table->foreignId('planning_decided_by_user_id')->nullable()->after('planning_decided_at')->constrained('users')->nullOnDelete();
            }
        });

        DB::table('project_transfer_requests')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $directorStatus = match ((string) $row->status) {
                    'approved' => 'approved',
                    'rejected' => 'rejected',
                    default => 'pending',
                };

                DB::table('project_transfer_requests')
                    ->where('id', $row->id)
                    ->update([
                        'director_status' => $directorStatus,
                        'director_note' => $row->decision_note,
                        'director_decided_at' => $row->decided_at,
                        'director_decided_by_user_id' => $row->decided_by_user_id,
                        'planning_status' => 'pending',
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_transfer_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('project_transfer_requests', 'planning_decided_by_user_id')) {
                $table->dropConstrainedForeignId('planning_decided_by_user_id');
            }
            if (Schema::hasColumn('project_transfer_requests', 'planning_decided_at')) {
                $table->dropColumn(['planning_decided_at', 'planning_note', 'planning_status']);
            }
            if (Schema::hasColumn('project_transfer_requests', 'director_decided_by_user_id')) {
                $table->dropConstrainedForeignId('director_decided_by_user_id');
            }
            if (Schema::hasColumn('project_transfer_requests', 'director_decided_at')) {
                $table->dropColumn(['director_decided_at', 'director_note', 'director_status']);
            }
        });
    }
};
