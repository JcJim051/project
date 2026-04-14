<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('requirement_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained('requirements')->cascadeOnDelete();
            $table->string('drive_file_id')->nullable()->index();
            $table->string('drive_file_name');
            $table->string('drive_mime_type')->nullable();
            $table->dateTime('drive_modified_time')->nullable();
            $table->string('source')->default('drive');
            $table->boolean('in_drive')->default(true);
            $table->timestamps();

            $table->unique(['project_id', 'drive_file_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requirement_evidences');
    }
};
