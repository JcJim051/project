<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_requirement', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable()->after('requirement_id');
        });

        DB::table('project_requirement')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $project = DB::table('projects')->where('id', $row->project_id)->first(['created_at', 'fecha_creacion']);
                DB::table('project_requirement')->where('id', $row->id)->update([
                    'activated_at' => $row->created_at ?: ($project->created_at ?? $project->fecha_creacion ?? now()),
                ]);
            }
        });

        Schema::table('plane_task_links', function (Blueprint $table) {
            $table->foreignId('operational_cycle_id')->nullable()->after('operational_module_id')->constrained('operational_cycles')->nullOnDelete();
            $table->foreignId('operational_activity_type_id')->nullable()->after('operational_cycle_id')->constrained('operational_activity_types')->nullOnDelete();
            $table->timestamp('activated_at')->nullable()->after('assignment_note');
            $table->date('planned_start_date')->nullable()->after('activated_at');
            $table->date('planned_target_date')->nullable()->after('planned_start_date');
            $table->string('plane_cycle_id')->nullable()->after('planned_target_date');
            $table->json('plane_label_ids')->nullable()->after('plane_cycle_id');
            $table->string('current_state_code', 80)->nullable()->after('plane_label_ids');
            $table->timestamp('first_started_at')->nullable()->after('current_state_code');
            $table->timestamp('completed_at')->nullable()->after('first_started_at');
            $table->timestamp('deactivated_at')->nullable()->after('completed_at');
        });

        Schema::create('operational_activity_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plane_task_link_id')->nullable()->constrained('plane_task_links')->nullOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('source', 50)->default('orbit');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['project_id', 'event_type', 'occurred_at'], 'operational_events_project_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_activity_events');
        Schema::table('plane_task_links', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operational_activity_type_id');
            $table->dropConstrainedForeignId('operational_cycle_id');
            $table->dropColumn(['activated_at', 'planned_start_date', 'planned_target_date', 'plane_cycle_id', 'plane_label_ids', 'current_state_code', 'first_started_at', 'completed_at', 'deactivated_at']);
        });
        Schema::table('project_requirement', fn (Blueprint $table) => $table->dropColumn('activated_at'));
    }
};
