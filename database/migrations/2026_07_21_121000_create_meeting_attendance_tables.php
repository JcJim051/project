<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('public_token', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('objetivo')->nullable();
            $table->date('fecha')->nullable();
            $table->string('lugar')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_terminacion')->nullable();
            $table->string('template_version', 120)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'fecha']);
        });

        Schema::create('meeting_people', function (Blueprint $table): void {
            $table->id();
            $table->string('document_number', 100)->nullable();
            $table->string('document_number_normalized', 100)->nullable()->unique();
            $table->string('full_name');
            $table->string('organization_area')->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('email_or_address')->nullable();
            $table->string('person_kind', 20)->default('external');
            $table->string('internal_source_type', 40)->nullable();
            $table->unsignedBigInteger('internal_source_id')->nullable();
            $table->timestamps();

            $table->index(['full_name']);
            $table->index(['person_kind']);
            $table->index(['organization_area']);
            $table->index(['internal_source_type', 'internal_source_id']);
        });

        Schema::create('meeting_attendance_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('meeting_attendance_sessions')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('meeting_people')->cascadeOnDelete();
            $table->string('document_number', 100)->nullable();
            $table->string('document_number_normalized', 100)->nullable();
            $table->string('full_name');
            $table->string('organization_area')->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('email_or_address')->nullable();
            $table->string('signature_path')->nullable();
            $table->unsignedInteger('sequence_number')->default(1);
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'document_number_normalized'], 'meeting_attendance_unique_session_document');
            $table->index(['person_id', 'registered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_attendance_entries');
        Schema::dropIfExists('meeting_people');
        Schema::dropIfExists('meeting_attendance_sessions');
    }
};
