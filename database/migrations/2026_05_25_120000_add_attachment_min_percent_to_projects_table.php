<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedTinyInteger('attachments_min_percent')
                ->default(80)
                ->after('drive_folder_id');
        });

        DB::table('projects')
            ->whereNull('attachments_min_percent')
            ->update(['attachments_min_percent' => 80]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('attachments_min_percent');
        });
    }
};

