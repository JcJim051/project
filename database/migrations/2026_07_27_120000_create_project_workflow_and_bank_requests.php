<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $table): void {
            $table->string('version', 40)->nullable()->after('file_kind');
            $table->boolean('is_active')->default(true)->after('version');
            $table->date('effective_at')->nullable()->after('is_active');
            $table->json('sheet_config')->nullable()->after('effective_at');
            $table->softDeletes();
        });

        Schema::create('project_workflow_stages', function (Blueprint $table): void {
            $table->id();
            $table->string('funding_source', 20);
            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_optional')->default(false);
            $table->string('optional_rule', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['funding_source', 'slug']);
        });

        Schema::create('project_workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stage_id')->constrained('project_workflow_stages')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['stage_id', 'slug']);
        });

        Schema::create('project_workflow_step_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('step_id')->constrained('project_workflow_steps')->cascadeOnDelete();
            $table->foreignId('requirement_id')->constrained('requirements')->cascadeOnDelete();
            $table->boolean('is_required')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['step_id', 'requirement_id'], 'workflow_step_requirement_unique');
        });

        Schema::create('project_workflow_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('step_id')->constrained('project_workflow_steps')->cascadeOnDelete();
            $table->boolean('applicability_override')->nullable();
            $table->foreignId('validated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('validated_role', 40)->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->text('validation_note')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'step_id']);
        });

        Schema::create('project_bank_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('document_template_id')->nullable()->constrained('document_templates')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('variant', 20);
            $table->unsignedInteger('version_number');
            $table->string('generation_type', 20)->default('initial');
            $table->string('status', 30)->default('generated');
            $table->json('form_data');
            $table->text('update_reason')->nullable();
            $table->string('output_filename')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'variant', 'version_number'], 'bank_request_project_variant_version_unique');
        });

        $this->seedWorkflow();
    }

    public function down(): void
    {
        Schema::dropIfExists('project_bank_requests');
        Schema::dropIfExists('project_workflow_states');
        Schema::dropIfExists('project_workflow_step_requirements');
        Schema::dropIfExists('project_workflow_steps');
        Schema::dropIfExists('project_workflow_stages');

        Schema::table('document_templates', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['version', 'is_active', 'effective_at', 'sheet_config']);
        });
    }

    private function seedWorkflow(): void
    {
        $definitions = [
            'sgr' => [
                ['Estructuración', [], false],
                ['Preparación institucional', [
                    'Declaratoria de importancia estratégica',
                    'Documentos del banco',
                    'Ficha cumple',
                ], false],
                ['Vigencias futuras', ['CODFIS de vigencias futuras'], true],
                ['Viabilidad y aprobación', [
                    'Documentos de viabilidad',
                    'Designación del ejecutor',
                    'Aprobación',
                ], false],
                ['Incorporación', ['Incorporación'], false],
                ['Banco de Programas y Proyectos', ['Solicitud del Banco de Programas y Proyectos'], false],
            ],
            'propios' => [
                ['Estructuración', [], false],
                ['Banco y viabilidad', [
                    'Documentos del banco',
                    'Viabilidad',
                ], false],
                ['Incorporación presupuestal', [
                    'Incorporación presupuestal',
                    'Ajuste de incorporación presupuestal',
                ], false],
                ['Vigencias futuras', [
                    'Declaratoria de importancia estratégica',
                    'CODFIS de vigencias futuras',
                    'Ordenanza de vigencias futuras',
                ], true],
                ['Banco de Programas y Proyectos', ['Solicitud del Banco de Programas y Proyectos'], false],
            ],
        ];

        foreach ($definitions as $fundingSource => $stages) {
            foreach ($stages as $stageIndex => [$stageName, $steps, $optional]) {
                $stageId = DB::table('project_workflow_stages')->insertGetId([
                    'funding_source' => $fundingSource,
                    'name' => $stageName,
                    'slug' => Str::slug($stageName),
                    'sort_order' => $stageIndex + 1,
                    'is_optional' => $optional,
                    'optional_rule' => $optional ? 'multiple_execution_years' : null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($steps as $stepIndex => $stepName) {
                    $stepId = DB::table('project_workflow_steps')->insertGetId([
                        'stage_id' => $stageId,
                        'name' => $stepName,
                        'slug' => Str::slug($stepName),
                        'sort_order' => $stepIndex + 1,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $requirementId = DB::table('requirements')->insertGetId([
                        'codigo_interno' => strtoupper($fundingSource).'-POST-'.($stageIndex + 1).'.'.($stepIndex + 1),
                        'texto' => $stepName,
                        'tipo' => 'Flujo posterior',
                        'requiere_check' => 'SI',
                        'orden' => (string) ($stepIndex + 1),
                        'numeracion' => ($stageIndex + 1).'.'.($stepIndex + 1),
                        'requisito' => $stepName,
                        'nombre_documento' => $stepName,
                        'carpeta' => $stageName,
                        'evidence_format_rule' => 'cualquiera',
                        'origen' => 'workflow_post_structure',
                        'visible' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('project_workflow_step_requirements')->insert([
                        'step_id' => $stepId,
                        'requirement_id' => $requirementId,
                        'is_required' => true,
                        'sort_order' => 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }
};
