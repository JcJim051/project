<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_onboarding_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('public_token', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('team_onboarding_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('team_onboarding_campaigns')->cascadeOnDelete();
            $table->string('requested_role', 50);
            $table->string('document_number', 100);
            $table->string('document_number_normalized', 100)->nullable()->index();
            $table->string('full_name');
            $table->string('phone', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('municipio', 255)->nullable();
            $table->string('organization_area', 255)->nullable();
            $table->string('specialty', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('review_notes')->nullable();
            $table->foreignId('approved_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('created_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_specialist_id')->nullable()->constrained('specialists')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_onboarding_requests');
        Schema::dropIfExists('team_onboarding_campaigns');
    }
};
