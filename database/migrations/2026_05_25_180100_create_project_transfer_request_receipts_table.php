<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_transfer_request_receipts')) {
            return;
        }

        Schema::create('project_transfer_request_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_transfer_request_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('ack_note')->nullable();
            $table->timestamps();

            $table->unique(['project_transfer_request_id', 'user_id'], 'ptr_receipts_unique_user_request');
            $table->foreign('project_transfer_request_id', 'ptr_receipts_request_fk')
                ->references('id')->on('project_transfer_requests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('project_transfer_request_receipts')) {
            return;
        }

        Schema::dropIfExists('project_transfer_request_receipts');
    }
};
