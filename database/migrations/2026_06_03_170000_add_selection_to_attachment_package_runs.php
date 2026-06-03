<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->json('attachment_package_selection')->nullable()->after('attachments_min_percent');
        });

        Schema::table('attachment_package_runs', function (Blueprint $table) {
            $table->json('selected_documents')->nullable()->after('progress_percent_snapshot');
            $table->string('output_type', 20)->nullable()->after('selected_documents');
            $table->string('output_filename')->nullable()->after('zip_local_path');
            $table->string('output_local_path')->nullable()->after('output_filename');
        });
    }

    public function down(): void
    {
        Schema::table('attachment_package_runs', function (Blueprint $table) {
            $table->dropColumn(['selected_documents', 'output_type', 'output_filename', 'output_local_path']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('attachment_package_selection');
        });
    }
};
