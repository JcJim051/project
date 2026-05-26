<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('point_activities', 'image_path')) {
            Schema::table('point_activities', function (Blueprint $table) {
                $table->dropColumn('image_path');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('point_activities', 'image_path')) {
            Schema::table('point_activities', function (Blueprint $table) {
                $table->string('image_path')->nullable()->after('description');
            });
        }
    }
};

