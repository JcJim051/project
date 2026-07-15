<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_plane_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_cycle_id')->constrained('operational_cycles')->restrictOnDelete();
            $table->string('plane_cycle_id')->nullable();
            $table->string('plane_project_id')->nullable();
            $table->string('name_snapshot');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 30)->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'operational_cycle_id'], 'project_plane_cycle_unique');
        });

        Schema::create('project_plane_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_label_id')->constrained('operational_labels')->restrictOnDelete();
            $table->string('plane_label_id')->nullable();
            $table->string('plane_project_id')->nullable();
            $table->string('name_snapshot');
            $table->string('status', 30)->default('pending');
            $table->text('sync_error')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'operational_label_id'], 'project_plane_label_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_plane_labels');
        Schema::dropIfExists('project_plane_cycles');
    }
};
