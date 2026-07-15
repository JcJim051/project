<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operational_activity_mappings', function (Blueprint $table) {
            if (! Schema::hasColumn('operational_activity_mappings', 'plane_priority')) {
                $table->string('plane_priority', 20)->default('medium')->after('descripcion_operativa');
            }

            if (! Schema::hasColumn('operational_activity_mappings', 'responsible_type')) {
                $table->string('responsible_type', 40)->default('sin_responsable')->after('plane_priority');
            }
        });
    }

    public function down(): void
    {
        Schema::table('operational_activity_mappings', function (Blueprint $table) {
            if (Schema::hasColumn('operational_activity_mappings', 'responsible_type')) {
                $table->dropColumn('responsible_type');
            }

            if (Schema::hasColumn('operational_activity_mappings', 'plane_priority')) {
                $table->dropColumn('plane_priority');
            }
        });
    }
};
