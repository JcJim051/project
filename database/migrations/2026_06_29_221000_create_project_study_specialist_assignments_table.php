<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_study_specialist_assignments')) {
            return;
        }

        Schema::create('project_study_specialist_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('study_folder');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'study_folder'], 'project_study_specialist_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_study_specialist_assignments');
    }
};
