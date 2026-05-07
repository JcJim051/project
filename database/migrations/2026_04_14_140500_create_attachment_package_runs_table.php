<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachment_package_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->unsignedTinyInteger('progress_percent_snapshot')->default(0);
            $table->unsignedInteger('version_number')->nullable();
            $table->string('zip_filename')->nullable();
            $table->string('zip_local_path')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->unsignedInteger('generated_pdf_count')->default(0);
            $table->unsignedInteger('missing_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachment_package_runs');
    }
};

