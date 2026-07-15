<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('operational_activity_mappings', 'operational_cycle_id')) {
            Schema::table('operational_activity_mappings', fn (Blueprint $table) => $table->foreignId('operational_cycle_id')->nullable()->after('operational_module_id'));
        }
        if (! Schema::hasColumn('operational_activity_mappings', 'operational_activity_type_id')) {
            Schema::table('operational_activity_mappings', fn (Blueprint $table) => $table->foreignId('operational_activity_type_id')->nullable()->after('operational_cycle_id'));
        }
        if (! Schema::hasColumn('operational_activity_mappings', 'planned_start_rule')) {
            Schema::table('operational_activity_mappings', function (Blueprint $table) {
                $table->string('planned_start_rule', 30)->default('activation')->after('responsible_type');
                $table->integer('start_offset_days')->default(0)->after('planned_start_rule');
                $table->unsignedInteger('default_duration_days')->nullable()->after('start_offset_days');
                $table->boolean('track_as_kpi')->default(true)->after('default_duration_days');
            });
        }

        if (DB::getDriverName() !== 'sqlite') {
            $foreignKeys = collect(DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'operational_activity_mappings' AND CONSTRAINT_TYPE = 'FOREIGN KEY'"))
                ->pluck('CONSTRAINT_NAME');
            Schema::table('operational_activity_mappings', function (Blueprint $table) use ($foreignKeys) {
                if (! $foreignKeys->contains('oam_cycle_fk') && ! $foreignKeys->contains('operational_activity_mappings_operational_cycle_id_foreign')) {
                    $table->foreign('operational_cycle_id', 'oam_cycle_fk')->references('id')->on('operational_cycles')->nullOnDelete();
                }
                if (! $foreignKeys->contains('oam_activity_type_fk')) {
                    $table->foreign('operational_activity_type_id', 'oam_activity_type_fk')->references('id')->on('operational_activity_types')->nullOnDelete();
                }
            });
        }

        if (! Schema::hasTable('operational_activity_mapping_label')) {
            Schema::create('operational_activity_mapping_label', function (Blueprint $table) {
                $table->id();
                $table->foreignId('operational_activity_mapping_id');
                $table->foreignId('operational_label_id');
                $table->timestamps();
            });
        }

        if (DB::getDriverName() === 'sqlite') {
            Schema::table('operational_activity_mapping_label', fn (Blueprint $table) => $table->unique(['operational_activity_mapping_id', 'operational_label_id'], 'oam_label_unique'));
        } else {
            $pivotForeignKeys = collect(DB::select("SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'operational_activity_mapping_label' AND CONSTRAINT_TYPE = 'FOREIGN KEY'"))
                ->pluck('CONSTRAINT_NAME');
            $pivotIndexes = collect(DB::select("SHOW INDEX FROM operational_activity_mapping_label"))->pluck('Key_name');
            Schema::table('operational_activity_mapping_label', function (Blueprint $table) use ($pivotForeignKeys, $pivotIndexes) {
                if (! $pivotForeignKeys->contains('oam_label_mapping_fk')) {
                    $table->foreign('operational_activity_mapping_id', 'oam_label_mapping_fk')->references('id')->on('operational_activity_mappings')->cascadeOnDelete();
                }
                if (! $pivotForeignKeys->contains('oam_label_label_fk')) {
                    $table->foreign('operational_label_id', 'oam_label_label_fk')->references('id')->on('operational_labels')->cascadeOnDelete();
                }
                if (! $pivotIndexes->contains('oam_label_unique')) {
                    $table->unique(['operational_activity_mapping_id', 'operational_label_id'], 'oam_label_unique');
                }
            });
        }

        $requirementType = DB::table('operational_activity_types')->where('codigo', 'requisito')->value('id');
        $baseType = DB::table('operational_activity_types')->where('codigo', 'actividad_base')->value('id');
        DB::table('operational_activity_mappings')->where('source_type', 'requirement')->update(['operational_activity_type_id' => $requirementType]);
        DB::table('operational_activity_mappings')->where('source_type', 'generic')->update(['operational_activity_type_id' => $baseType]);
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_activity_mapping_label');
        Schema::table('operational_activity_mappings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operational_activity_type_id');
            $table->dropConstrainedForeignId('operational_cycle_id');
            $table->dropColumn(['planned_start_rule', 'start_offset_days', 'default_duration_days', 'track_as_kpi']);
        });
    }
};
