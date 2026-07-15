<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('plane_sync_runs')) {
            return;
        }

        Schema::create('plane_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 20)->default('full');
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('job_unique_key')->nullable();
            $table->text('message')->nullable();
            $table->longText('error_details')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index(['project_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plane_sync_runs');
    }
};
