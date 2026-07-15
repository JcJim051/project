<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plane_task_links')) {
            return;
        }

        Schema::create('plane_task_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('operational_module_id')->nullable()->constrained('operational_modules')->nullOnDelete();
            $table->foreignId('operational_activity_mapping_id')->nullable()->constrained('operational_activity_mappings')->nullOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->string('plane_project_id')->nullable();
            $table->string('plane_issue_id')->nullable();
            $table->string('plane_module_id')->nullable();
            $table->string('dedupe_key');
            $table->string('source_type', 30);
            $table->string('source_origin', 30)->default('catalog');
            $table->string('source_folder')->nullable();
            $table->string('source_title')->nullable();
            $table->string('title')->nullable();
            $table->string('status', 30)->default('active');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'dedupe_key'], 'plane_task_links_project_dedupe_unique');
            $table->index(['project_id', 'status'], 'plane_task_links_project_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plane_task_links');
    }
};
