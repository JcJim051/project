<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requirement_evidences', function (Blueprint $table) {
            $table->foreignId('linked_by_user_id')
                ->nullable()
                ->after('source')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('linked_at')->nullable()->after('linked_by_user_id');
            $table->text('link_note')->nullable()->after('linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('requirement_evidences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_by_user_id');
            $table->dropColumn(['linked_at', 'link_note']);
        });
    }
};
