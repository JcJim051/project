<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('operational_activity_mappings')) {
            return;
        }

        Schema::create('operational_activity_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operational_module_id')->constrained('operational_modules')->cascadeOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->string('source_type', 30)->default('requirement');
            $table->string('source_origin', 30)->default('catalog');
            $table->string('source_folder')->nullable();
            $table->boolean('repeat_per_study')->default(false);
            $table->string('titulo_operativo');
            $table->text('descripcion_operativa')->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->boolean('create_automatically')->default(true);
            $table->timestamps();

            $table->index(['operational_module_id', 'source_type', 'activo'], 'oam_module_type_active_idx');
            $table->index(['requirement_id', 'activo'], 'oam_requirement_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_activity_mappings');
    }
};
