<?php

namespace Tests\Feature;

use App\Filament\Resources\ProjectResource\Pages\ManageProject as ManageProjectPage;
use App\Filament\Resources\ProjectWorkflowStageResource\Pages\EditProjectWorkflowStage;
use App\Filament\Resources\ProjectWorkflowStageResource\RelationManagers\StepsRelationManager;
use App\Filament\Resources\ProjectWorkflowStepResource;
use App\Models\Project;
use App\Models\ProjectBankActivityRow;
use App\Models\ProjectBankProfile;
use App\Models\ProjectBankSignatory;
use App\Models\ProjectWorkflowStage;
use App\Models\ProjectWorkflowState;
use App\Models\ProjectWorkflowStep;
use App\Models\ProjectWorkflowStepRequirement;
use App\Models\Requirement;
use App\Models\RequirementEvidence;
use App\Models\User;
use App\Services\ProjectBankRequestService;
use App\Services\ProjectWorkflowService;
use App\Services\RequirementProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use ZipArchive;

class ProjectWorkflowAndBankRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_workflow_uses_funding_source_and_separates_completion_from_validation(): void
    {
        $project = Project::query()->create([
            'nombre' => 'Proyecto de prueba',
            'objeto_proyecto' => 'Objeto del proyecto de prueba',
            'id_proyecto' => 'ID-9001',
            'municipio' => 'Villavicencio',
            'funding_source' => 'sgr',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $service = app(ProjectWorkflowService::class);
        $stages = $service->buildForProject($project);

        $this->assertSame(
            ['Estructuración', 'Preparación institucional', 'Vigencias futuras', 'Viabilidad y aprobación', 'Incorporación', 'Banco de Programas y Proyectos', 'Precontractual'],
            $stages->pluck('name')->all()
        );
        $this->assertSame('not_applicable', $stages->firstWhere('name', 'Vigencias futuras')['status']);

        $step = $stages->firstWhere('name', 'Preparación institucional')['steps']->first();
        $requirement = $step['requirements']->first()['model'];
        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'drive_file_id' => 'drive-test-1',
            'drive_file_name' => 'declaratoria.pdf',
            'drive_folder_name' => $requirement->carpeta,
            'in_drive' => true,
        ]);

        $refreshedStep = $service->buildForProject($project)
            ->firstWhere('name', 'Preparación institucional')['steps']->first();
        $this->assertTrue($refreshedStep['complete']);
        $this->assertFalse($refreshedStep['validated']);

        $service->validateStep($project, $step['model']->id, $admin, 'Revisado');
        $validatedStep = $service->buildForProject($project)
            ->firstWhere('name', 'Preparación institucional')['steps']->first();
        $this->assertTrue($validatedStep['validated']);
        $this->assertSame('validated', $validatedStep['status']);
    }

    public function test_precontractual_workflow_seed_is_idempotent(): void
    {
        $migration = require database_path(
            'migrations/2026_07_28_220000_seed_precontractual_workflow_stages.php'
        );

        $migration->up();
        $migration->up();

        $stages = ProjectWorkflowStage::query()
            ->where('name', 'Precontractual')
            ->whereIn('funding_source', ['sgr', 'propios'])
            ->get();
        $requirement = Requirement::query()
            ->where('codigo_interno', 'WF-PRE-LIC')
            ->first();

        $this->assertCount(2, $stages);
        $this->assertNotNull($requirement);
        $this->assertSame(
            2,
            ProjectWorkflowStep::query()
                ->whereIn('stage_id', $stages->pluck('id'))
                ->where('completion_rule', ProjectWorkflowStep::COMPLETION_RULE_LICENSE_PERMIT_DEFINITIVES)
                ->count()
        );
        $this->assertSame(
            2,
            ProjectWorkflowStepRequirement::query()
                ->where('requirement_id', $requirement->id)
                ->whereIn(
                    'step_id',
                    ProjectWorkflowStep::query()->whereIn('stage_id', $stages->pluck('id'))->pluck('id')
                )
                ->count()
        );
    }

    public function test_fbs01_generation_uses_project_id_and_preserves_workbook_assets(): void
    {
        $project = Project::query()->create([
            'nombre' => 'Proyecto FBS',
            'objeto_proyecto' => 'Objeto del proyecto FBS',
            'id_proyecto' => 'ID-123456',
            'municipio' => 'Villavicencio',
            'funding_source' => 'propios',
            'valor' => 2500000,
        ]);
        ProjectBankProfile::query()->create([
            'project_id' => $project->id,
            'codigo_dependencia' => '26',
            'dependencia' => 'AGENCIA PARA LA INFRAESTRUCTURA DEL META',
            'horizonte_anio_0' => 2026,
            'vigencia' => 2026,
            'codigo_fuente' => '01',
            'nombre_fuente' => 'Recursos propios',
            'meta_plan_codigo' => 'META-1',
            'meta_plan_nombre' => 'Meta del plan',
        ]);
        ProjectBankActivityRow::query()->create([
            'project_id' => $project->id,
            'orden' => 1,
            'producto_mga' => 'Producto MGA',
            'actividad' => 'REALIZAR OBRA',
            'valor_actividad' => 2500000,
        ]);
        ProjectBankSignatory::query()->create([
            'project_id' => $project->id,
            'role' => 'formulador_oficial',
            'orden' => 1,
            'nombre' => 'Ordenador del gasto',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        foreach ([
            'obra' => ['sheet' => ' OBRA', 'id_cell' => 'Y25', 'name_cell' => 'G25'],
            'inter' => ['sheet' => ' INTER', 'id_cell' => 'Y22', 'name_cell' => 'G22'],
            'apoyo' => ['sheet' => 'APOYO', 'id_cell' => 'Y21', 'name_cell' => 'G21'],
        ] as $variant => $expected) {
            $data = $this->requestData();
            $data['variant'] = $variant;
            $generated = app(ProjectBankRequestService::class)->create($project, $data, null);
            $path = $generated['path'];

            try {
                $spreadsheet = IOFactory::load($path);
                $sheet = $spreadsheet->getSheetByName($expected['sheet']);
                $this->assertNotNull($sheet);
                $this->assertSame('ID-123456', $sheet->getCell($expected['id_cell'])->getValue());
                $this->assertSame('Proyecto FBS', $sheet->getCell($expected['name_cell'])->getValue());

                $zip = new ZipArchive;
                $this->assertTrue($zip->open($path) === true);
                $entries = [];
                for ($index = 0; $index < $zip->numFiles; $index++) {
                    $entries[] = $zip->getNameIndex($index);
                }
                $zip->close();

                $this->assertNotEmpty(array_filter($entries, fn ($name) => str_starts_with($name, 'xl/media/')));
                $this->assertNotEmpty(array_filter($entries, fn ($name) => str_starts_with($name, 'xl/drawings/')));
                $this->assertCount(3, array_filter($entries, fn ($name) => str_starts_with($name, 'xl/printerSettings/')));
                $this->assertNotSame('', $sheet->getPageSetup()->getPrintArea());
                $this->assertSame([1, 3], $sheet->getPageSetup()->getRowsToRepeatAtTop());
            } finally {
                File::deleteDirectory(dirname($path));
            }
        }
    }

    public function test_bank_documents_and_bank_request_have_separate_pages(): void
    {
        $documentsUrl = route('filament.admin.resources.projects.bank', ['record' => 321]);
        $requestUrl = route('filament.admin.resources.projects.bank-request', ['record' => 321]);

        $this->assertSame('/panel/projects/321/banco', parse_url($documentsUrl, PHP_URL_PATH));
        $this->assertSame('/panel/projects/321/solicitud-banco', parse_url($requestUrl, PHP_URL_PATH));
        $this->assertNotSame($documentsUrl, $requestUrl);
    }

    public function test_macro_stage_unifies_elements_and_preserves_historical_states(): void
    {
        $stage = ProjectWorkflowStage::query()->create([
            'funding_source' => 'sgr',
            'name' => 'Precontractual',
            'slug' => 'precontractual-prueba',
            'sort_order' => 90,
            'is_optional' => false,
            'is_active' => true,
        ]);
        $step = ProjectWorkflowStep::query()->create([
            'stage_id' => $stage->id,
            'name' => 'Revisión de licencias y permisos',
            'slug' => 'revision-licencias-prueba',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $requirement = Requirement::query()->create([
            'codigo_interno' => 'PRE-01',
            'orden' => '1',
            'nombre_documento' => 'Licencias y permisos',
            'carpeta' => 'Precontractual',
            'visible' => true,
        ]);
        ProjectWorkflowStepRequirement::query()->create([
            'step_id' => $step->id,
            'requirement_id' => $requirement->id,
            'is_required' => true,
            'sort_order' => 1,
        ]);

        $this->assertSame([$step->id], $stage->steps()->pluck('id')->all());
        $this->assertSame([$requirement->id], $step->requirements()->pluck('requirements.id')->all());
        $this->assertTrue($step->canBeDeletedSafely());
        $this->assertTrue($stage->canBeDeletedSafely());
        $this->assertFalse(ProjectWorkflowStepResource::shouldRegisterNavigation());

        $legacyUrl = route('filament.admin.resources.project-workflow-steps.edit', ['record' => $step]);
        $this->assertStringContainsString("/panel/project-workflow-steps/{$step->id}/edit", $legacyUrl);

        $admin = User::factory()->create(['is_admin' => true]);
        Livewire::actingAs($admin)
            ->test(EditProjectWorkflowStage::class, ['record' => $stage->getRouteKey()])
            ->assertSuccessful();
        Livewire::actingAs($admin)
            ->test(StepsRelationManager::class, [
                'ownerRecord' => $stage,
                'pageClass' => EditProjectWorkflowStage::class,
            ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$step]);

        $project = Project::query()->create([
            'nombre' => 'Proyecto con trazabilidad',
            'objeto_proyecto' => 'Objeto de prueba',
            'id_proyecto' => 'ID-HIST-1',
            'municipio' => 'Villavicencio',
            'funding_source' => 'sgr',
        ]);
        ProjectWorkflowState::query()->create([
            'project_id' => $project->id,
            'step_id' => $step->id,
        ]);

        $this->assertFalse($step->canBeDeletedSafely());
        $this->assertFalse($stage->canBeDeletedSafely());
    }

    public function test_license_permit_classification_controls_structure_and_precontractual_follow_up(): void
    {
        foreach (['sgr', 'propios'] as $source) {
            $stage = ProjectWorkflowStage::query()->create([
                'funding_source' => $source,
                'name' => 'Precontractual pruebas',
                'slug' => 'precontractual-pruebas',
                'sort_order' => 90,
                'is_optional' => false,
                'is_active' => true,
            ]);
            ProjectWorkflowStep::query()->create([
                'stage_id' => $stage->id,
                'name' => 'Revisión de licencias y permisos',
                'slug' => 'revision-licencias-pruebas',
                'sort_order' => 1,
                'completion_rule' => ProjectWorkflowStep::COMPLETION_RULE_LICENSE_PERMIT_DEFINITIVES,
                'is_active' => true,
            ]);
        }

        $project = Project::query()->create([
            'nombre' => 'Proyecto con licencia',
            'objeto_proyecto' => 'Proyecto sujeto a licencia',
            'id_proyecto' => 'ID-LIC-1',
            'municipio' => 'Villavicencio',
            'funding_source' => 'sgr',
        ]);
        $requirement = Requirement::query()->create([
            'codigo_interno' => '04.01',
            'orden' => '1',
            'nombre_documento' => 'Licencia ambiental',
            'carpeta' => '04 Licencias y Permisos',
            'visible' => true,
        ]);
        $project->requisitos()->attach($requirement->id);
        $evidence = RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'drive_file_id' => 'license-file-1',
            'drive_file_name' => 'licencia.pdf',
            'drive_folder_name' => $requirement->carpeta,
            'in_drive' => true,
        ]);

        $progressService = app(RequirementProgressService::class);
        $analysis = $progressService->analyze(collect([$requirement]), collect([$evidence]));
        $this->assertFalse($analysis['requirements'][$requirement->id]['has_evidence']);

        $workflowService = app(ProjectWorkflowService::class);
        $followUp = $workflowService->buildForProject($project)
            ->flatMap(fn ($stage) => $stage['steps'])
            ->firstWhere('license_permit_follow_up', true);
        $this->assertNotNull($followUp);
        $this->assertSame('pending_structure', $followUp['requirements']->first()['follow_up_status']);
        $this->assertFalse($followUp['complete']);

        $evidence->update([
            'license_permit_status' => RequirementEvidence::LICENSE_PERMIT_APPLICATION,
        ]);
        $analysis = $progressService->analyze(collect([$requirement]), collect([$evidence->fresh()]));
        $this->assertTrue($analysis['requirements'][$requirement->id]['has_evidence']);

        $followUp = $workflowService->buildForProject($project)
            ->flatMap(fn ($stage) => $stage['steps'])
            ->firstWhere('license_permit_follow_up', true);
        $this->assertSame('definitive_pending', $followUp['requirements']->first()['follow_up_status']);
        $this->assertFalse($followUp['complete']);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'drive_file_id' => 'license-file-2',
            'drive_file_name' => 'licencia-expedida.pdf',
            'drive_folder_name' => $requirement->carpeta,
            'license_permit_status' => RequirementEvidence::LICENSE_PERMIT_ISSUED,
            'in_drive' => true,
        ]);
        $followUp = $workflowService->buildForProject($project)
            ->flatMap(fn ($stage) => $stage['steps'])
            ->firstWhere('license_permit_follow_up', true);
        $this->assertSame('definitive_loaded', $followUp['requirements']->first()['follow_up_status']);
        $this->assertTrue($followUp['complete']);

        $this->assertSame(
            2,
            ProjectWorkflowStep::query()
                ->where('completion_rule', ProjectWorkflowStep::COMPLETION_RULE_LICENSE_PERMIT_DEFINITIVES)
                ->whereHas('stage', fn ($query) => $query->where('name', 'Precontractual pruebas'))
                ->count()
        );
    }

    public function test_existing_license_evidence_can_be_classified_from_authenticated_endpoint(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $project = Project::query()->create([
            'nombre' => 'Proyecto clasificación',
            'objeto_proyecto' => 'Objeto',
            'id_proyecto' => 'ID-LIC-2',
            'municipio' => 'Villavicencio',
            'funding_source' => 'propios',
        ]);
        $requirement = Requirement::query()->create([
            'codigo_interno' => '04.02',
            'orden' => '2',
            'nombre_documento' => 'Permiso de ocupación',
            'carpeta' => '04 Licencias y Permisos',
            'visible' => true,
        ]);
        $project->requisitos()->attach($requirement->id);
        $evidence = RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'drive_file_id' => 'permit-file-1',
            'drive_file_name' => 'radicado.pdf',
            'drive_folder_name' => $requirement->carpeta,
            'in_drive' => true,
        ]);

        $response = $this->actingAs($admin)->patchJson(
            route('projects.requirements.classify_evidence', [$project, $requirement, $evidence]),
            ['license_permit_status' => RequirementEvidence::LICENSE_PERMIT_APPLICATION]
        );

        $response->assertOk()->assertJsonPath('license_permit_status', 'application');
        $this->assertSame('application', $evidence->fresh()->license_permit_status);
        $this->assertSame($admin->id, $evidence->fresh()->classified_by_user_id);
        $this->assertNotNull($evidence->fresh()->classified_at);

        Livewire::actingAs($admin)
            ->test(ManageProjectPage::class, ['record' => $project->getRouteKey()])
            ->assertSuccessful();
    }

    private function requestData(): array
    {
        return [
            'variant' => 'obra',
            'generation_type' => 'initial',
            'request_date' => '2026-07-27',
            'recipient_salutation' => 'Doctora',
            'recipient_name' => 'Destinataria',
            'recipient_title' => 'Gerente',
            'recipient_entity' => 'Gobernación del Meta',
            'subject' => 'Solicitud de certificado',
            'expense_object' => 'Objeto del gasto',
            'value_to_certify' => 2500000,
            'beneficiaries_total' => 100,
            'beneficiaries_rural' => 20,
            'beneficiaries_urban' => 80,
            'beneficiary_description' => 'Descripción de beneficiarios',
            'other_results' => 'Resultados',
            'budget_tracer' => 'no_aplica',
            'differential' => array_fill(0, 4, ['men' => 10, 'women' => 10]),
            'pertinence' => 'Pertinencia',
            'legal_framework' => 'Marco legal',
            'market_study' => 'Estudio de mercado',
            'observations' => 'Observaciones',
        ];
    }
}
