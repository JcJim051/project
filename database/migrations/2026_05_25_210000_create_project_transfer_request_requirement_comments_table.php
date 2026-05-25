<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('project_transfer_request_requirement_comments')) {
            return;
        }

        Schema::create('project_transfer_request_requirement_comments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('project_transfer_request_id');
            $table->unsignedBigInteger('requirement_id');
            $table->unsignedBigInteger('author_user_id');
            $table->text('comment');
            $table->timestamps();

            $table->foreign('project_transfer_request_id', 'ptrrc_ptr_fk')
                ->references('id')
                ->on('project_transfer_requests')
                ->cascadeOnDelete();
            $table->foreign('requirement_id', 'ptrrc_req_fk')
                ->references('id')
                ->on('requirements')
                ->cascadeOnDelete();
            $table->foreign('author_user_id', 'ptrrc_user_fk')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->unique(['project_transfer_request_id', 'requirement_id'], 'ptr_req_comment_unique');
            $table->index(['project_transfer_request_id', 'requirement_id'], 'ptr_req_comment_lookup');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('project_transfer_request_requirement_comments')) {
            return;
        }

        Schema::dropIfExists('project_transfer_request_requirement_comments');
    }
};
