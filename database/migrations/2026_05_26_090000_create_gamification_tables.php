<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('point_activities', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('points')->default(0);
            $table->string('role_scope', 30)->default('ambos');
            $table->string('trigger_type', 30)->default('backend_event');
            $table->string('uniqueness_scope', 40)->default('once_per_season');
            $table->string('season_mode', 20)->default('annual');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('user_point_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('requirement_id')->nullable()->constrained('requirements')->nullOnDelete();
            $table->foreignId('point_activity_id')->constrained('point_activities')->cascadeOnDelete();
            $table->string('activity_code');
            $table->string('activity_name');
            $table->integer('points');
            $table->unsignedSmallInteger('season_year');
            $table->timestamp('awarded_at');
            $table->string('uniqueness_scope', 40);
            $table->string('event_key', 191);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['season_year', 'user_id']);
            $table->index(['awarded_at']);
            $table->unique(['season_year', 'user_id', 'activity_code', 'event_key'], 'upe_unique_event');
        });

        Schema::create('point_activity_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('point_activity_id')->constrained('point_activities')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 20);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamps();
        });

        DB::table('point_activities')->insert([
            [
                'code' => 'req_first_valid_evidence',
                'name' => 'Primera evidencia válida',
                'description' => 'Primera evidencia válida por requisito.',
                'enabled' => true,
                'points' => 8,
                'role_scope' => 'ambos',
                'trigger_type' => 'backend_event',
                'uniqueness_scope' => 'once_per_requirement',
                'season_mode' => 'annual',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'mga_submitted',
                'name' => 'Envío a evaluación MGA',
                'description' => 'Proyecto enviado a revisión interna MGA.',
                'enabled' => true,
                'points' => 30,
                'role_scope' => 'ambos',
                'trigger_type' => 'backend_event',
                'uniqueness_scope' => 'once_per_project',
                'season_mode' => 'annual',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'mga_approved',
                'name' => 'Aprobación MGA interna',
                'description' => 'Solicitud aprobada en revisión interna.',
                'enabled' => true,
                'points' => 60,
                'role_scope' => 'ambos',
                'trigger_type' => 'backend_event',
                'uniqueness_scope' => 'once_per_project',
                'season_mode' => 'annual',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'pdf_package_generated',
                'name' => 'Carteras generadas',
                'description' => 'Generación exitosa de paquete PDF.',
                'enabled' => true,
                'points' => 20,
                'role_scope' => 'ambos',
                'trigger_type' => 'backend_event',
                'uniqueness_scope' => 'once_per_project',
                'season_mode' => 'annual',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'transferido_mga',
                'name' => 'Transferido a MGA',
                'description' => 'Transferencia automática finalizada en éxito.',
                'enabled' => false,
                'points' => 40,
                'role_scope' => 'ambos',
                'trigger_type' => 'backend_event',
                'uniqueness_scope' => 'once_per_project',
                'season_mode' => 'annual',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('point_activity_audits');
        Schema::dropIfExists('user_point_events');
        Schema::dropIfExists('point_activities');
    }
};
