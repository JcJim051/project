<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirement_evidences', function (Blueprint $table): void {
            $table->string('license_permit_status', 30)->nullable()->after('link_note');
            $table->foreignId('classified_by_user_id')->nullable()->after('license_permit_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('classified_at')->nullable()->after('classified_by_user_id');
        });

        Schema::table('drive_upload_sessions', function (Blueprint $table): void {
            $table->string('license_permit_status', 30)->nullable()->after('mime_type');
        });

        Schema::table('project_workflow_steps', function (Blueprint $table): void {
            $table->string('completion_rule', 60)->nullable()->after('description');
        });

        $stepIds = DB::table('project_workflow_steps')
            ->join('project_workflow_stages', 'project_workflow_stages.id', '=', 'project_workflow_steps.stage_id')
            ->where('project_workflow_stages.name', 'Precontractual')
            ->where('project_workflow_steps.name', 'Revisión de licencias y permisos')
            ->pluck('project_workflow_steps.id');

        DB::table('project_workflow_steps')
            ->whereIn('id', $stepIds)
            ->update([
                'completion_rule' => 'license_permit_definitives',
                'updated_at' => now(),
            ]);

        // Existing validations predate document classification and must be reviewed again.
        DB::table('project_workflow_states')
            ->whereIn('step_id', $stepIds)
            ->update([
                'validated_by_user_id' => null,
                'validated_role' => null,
                'validated_at' => null,
                'validation_note' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('project_workflow_steps', function (Blueprint $table): void {
            $table->dropColumn('completion_rule');
        });

        Schema::table('drive_upload_sessions', function (Blueprint $table): void {
            $table->dropColumn('license_permit_status');
        });

        Schema::table('requirement_evidences', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('classified_by_user_id');
            $table->dropColumn(['license_permit_status', 'classified_at']);
        });
    }
};
