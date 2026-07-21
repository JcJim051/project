<?php

namespace Tests\Unit;

use App\Http\Controllers\AttachmentPackageRunController;
use App\Models\AttachmentPackageRun;
use App\Models\AttachmentPackageSection;
use App\Models\Project;
use App\Models\Requirement;
use App\Models\RequirementEvidence;
use App\Services\AttachmentPackageService;
use App\Services\GoogleDriveService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttachmentPackageServiceTest extends TestCase
{
    private FakeAttachmentPackageDriveService $fakeDrive;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        app('db')->purge('sqlite');
        app('db')->reconnect('sqlite');

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->timestamps();
        });

        Schema::create('requirements', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo_interno')->nullable();
            $table->string('numeracion')->nullable();
            $table->string('orden')->nullable();
            $table->string('requisito')->nullable();
            $table->string('nombre_documento')->nullable();
            $table->string('carpeta')->nullable();
            $table->boolean('visible')->default(true);
            $table->timestamps();
        });

        Schema::create('project_requirement', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('requirement_id');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('requirement_evidences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id');
            $table->foreignId('requirement_id');
            $table->string('drive_file_id')->nullable();
            $table->string('drive_file_name')->nullable();
            $table->string('drive_mime_type')->nullable();
            $table->timestamp('drive_modified_time')->nullable();
            $table->string('drive_folder_name')->nullable();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('linked_by_user_id')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->text('link_note')->nullable();
            $table->boolean('in_drive')->default(true);
            $table->timestamps();
        });

        Schema::create('attachment_package_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->nullable();
            $table->unsignedInteger('progress_percent_snapshot')->nullable();
            $table->text('selected_documents')->nullable();
            $table->string('output_type')->nullable();
            $table->unsignedInteger('version_number')->nullable();
            $table->string('zip_filename')->nullable();
            $table->string('zip_local_path')->nullable();
            $table->string('output_filename')->nullable();
            $table->string('output_local_path')->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->string('drive_file_id')->nullable();
            $table->unsignedInteger('generated_pdf_count')->nullable();
            $table->unsignedInteger('missing_count')->nullable();
            $table->text('error_message')->nullable();
            $table->text('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('attachment_package_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('name');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('active')->default(true);
            $table->string('source_group_code', 5)->nullable();
            $table->string('source_folder')->nullable();
            $table->string('recursive_root_folder')->nullable();
            $table->string('match_type', 40)->default('folder');
            $table->text('code_prefixes')->nullable();
            $table->text('allowed_extensions')->nullable();
            $table->boolean('include_all_folder_files')->default(false);
            $table->text('recursive_source_folders')->nullable();
            $table->timestamps();
        });

        $this->fakeDrive = new FakeAttachmentPackageDriveService();
        app()->instance(GoogleDriveService::class, $this->fakeDrive);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testTempRoot());
        File::deleteDirectory(storage_path('app/tmp/attachment-runs'));
        parent::tearDown();
    }

    public function test_recursive_subfolder_pdfs_are_embedded_as_bundle_attachments_inside_budget_document(): void
    {
        $project = Project::query()->create([
            'nombre' => 'Proyecto Presupuesto',
            'drive_folder_id' => 'drive-root',
        ]);

        $parent = AttachmentPackageSection::query()->create([
            'name' => '02 Presupuesto',
            'orden' => 20,
            'active' => true,
            'source_group_code' => '02',
            'match_type' => 'group',
        ]);

        AttachmentPackageSection::query()->create([
            'parent_id' => $parent->id,
            'name' => '2 Presupuesto CT',
            'orden' => 10,
            'active' => true,
            'source_group_code' => '02',
            'match_type' => 'group_code',
            'recursive_root_folder' => '02 Presupuesto',
            'recursive_source_folders' => ['2.1 Presupuesto', '2.4 Estudio de Mercado', '2.6 Programación'],
        ]);

        $requirement = Requirement::query()->create([
            'codigo_interno' => '2.0',
            'orden' => '2.0',
            'nombre_documento' => 'Presupuesto Excel',
            'carpeta' => '02 Presupuesto',
            'visible' => true,
        ]);
        $project->requisitos()->attach($requirement->id);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'drive_file_id' => 'excel-old',
            'drive_file_name' => 'Presupuesto Excel anterior.xlsx',
            'drive_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'in_drive' => true,
        ]);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'drive_file_id' => 'excel-1',
            'drive_file_name' => 'Presupuesto Excel.xlsx',
            'drive_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'in_drive' => true,
        ]);

        $recursiveRequirement = Requirement::query()->create([
            'codigo_interno' => '2.1',
            'orden' => '2.1',
            'nombre_documento' => 'Presupuesto base',
            'carpeta' => '2.1 Presupuesto',
            'visible' => true,
        ]);
        $project->requisitos()->attach($recursiveRequirement->id);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $recursiveRequirement->id,
            'drive_file_id' => 'linked-1',
            'drive_file_name' => '2.1 Resumen.pdf',
            'drive_mime_type' => 'application/pdf',
            'in_drive' => true,
        ]);

        $budgetExcelRequirement = Requirement::query()->create([
            'codigo_interno' => '2.1.1',
            'orden' => '2.1.1',
            'nombre_documento' => 'Presupuesto Excel',
            'carpeta' => '2.1 Presupuesto',
            'visible' => true,
        ]);
        $project->requisitos()->attach($budgetExcelRequirement->id);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $budgetExcelRequirement->id,
            'drive_file_id' => 'linked-xlsx',
            'drive_file_name' => '2.1 Presupuesto Excel.xlsx',
            'drive_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'in_drive' => true,
        ]);

        foreach ([
            ['2.2', 'Memorias'],
            ['2.3', 'APUS'],
            ['2.5', 'Especificaciones Tecnicas'],
        ] as [$code, $name]) {
            $req = Requirement::query()->create([
                'codigo_interno' => $code,
                'orden' => $code,
                'nombre_documento' => $name,
                'carpeta' => '02 Presupuesto',
                'visible' => true,
            ]);
            $project->requisitos()->attach($req->id);
        }

        $marketBundleRequirement = Requirement::query()->create([
            'codigo_interno' => '2.4',
            'orden' => '2.4',
            'nombre_documento' => 'Estudio de Mercado',
            'carpeta' => '2.4 Estudio de Mercado',
            'visible' => true,
        ]);
        $project->requisitos()->attach($marketBundleRequirement->id);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $marketBundleRequirement->id,
            'drive_file_id' => 'loose-2',
            'drive_file_name' => '2.4 Mercado.pdf',
            'drive_mime_type' => 'application/pdf',
            'in_drive' => true,
        ]);

        $marketRequirement = Requirement::query()->create([
            'codigo_interno' => '2.4.2',
            'orden' => '2.4.2',
            'nombre_documento' => 'Estudio de Mercado Excel',
            'carpeta' => '2.4 Estudio de Mercado',
            'visible' => true,
        ]);
        $project->requisitos()->attach($marketRequirement->id);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $marketRequirement->id,
            'drive_file_id' => 'market-xlsx',
            'drive_file_name' => '2.4 Estudio de Mercado.xlsx',
            'drive_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'in_drive' => true,
        ]);

        $programBundleRequirement = Requirement::query()->create([
            'codigo_interno' => '2.6',
            'orden' => '2.6',
            'nombre_documento' => 'Programacion',
            'carpeta' => '2.6 Programación',
            'visible' => true,
        ]);
        $project->requisitos()->attach($programBundleRequirement->id);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $programBundleRequirement->id,
            'drive_file_id' => 'loose-3',
            'drive_file_name' => '2.6 Programacion.pdf',
            'drive_mime_type' => 'application/pdf',
            'in_drive' => true,
        ]);

        $programRequirement = Requirement::query()->create([
            'codigo_interno' => '2.6.1',
            'orden' => '2.6.1',
            'nombre_documento' => 'Programacion Project',
            'carpeta' => '2.6 Programación',
            'visible' => true,
        ]);
        $project->requisitos()->attach($programRequirement->id);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $programRequirement->id,
            'drive_file_id' => 'project-mpp',
            'drive_file_name' => '2.6 Programacion Project.mpp',
            'drive_mime_type' => 'application/vnd.ms-project',
            'in_drive' => true,
        ]);

        $editableRequirement = Requirement::query()->create([
            'codigo_interno' => '2.6.2',
            'orden' => '2.6.2',
            'nombre_documento' => 'Programacion Editable',
            'carpeta' => 'Editables',
            'visible' => true,
        ]);
        $project->requisitos()->attach($editableRequirement->id);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $editableRequirement->id,
            'drive_file_id' => 'editable-1',
            'drive_file_name' => 'Editable fuente.xlsx',
            'drive_folder_name' => 'Editables',
            'drive_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'in_drive' => true,
        ]);

        $this->fakeDrive->recursiveResponses['02 Presupuesto|2.1 Presupuesto'] = [
            'folder_label' => '02 Presupuesto',
            'items' => collect([
                ['id' => 'linked-1', 'name' => '2.1 Resumen.pdf', 'mimeType' => 'application/pdf'],
            ]),
            'resolved_folders' => [
                ['name' => '2.1 Presupuesto', 'id' => 'folder-21'],
            ],
            'missing_folders' => [],
        ];
        $this->fakeDrive->recursiveResponses['02 Presupuesto|2.4 Estudio de Mercado'] = [
            'folder_label' => '02 Presupuesto',
            'items' => collect([
                ['id' => 'loose-2', 'name' => '2.4 Mercado.pdf', 'mimeType' => 'application/pdf'],
            ]),
            'resolved_folders' => [
                ['name' => '2.4 Estudio de Mercado', 'id' => 'folder-24'],
            ],
            'missing_folders' => [],
        ];
        $this->fakeDrive->recursiveResponses['02 Presupuesto|2.6 Programación'] = [
            'folder_label' => '02 Presupuesto',
            'items' => collect([
                ['id' => 'loose-3', 'name' => '2.6 Programacion.pdf', 'mimeType' => 'application/pdf'],
                ['id' => 'ignored-4', 'name' => '2.6 Programacion.docx', 'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                ['id' => 'unrelated-1', 'name' => 'ESP NR-097.docx', 'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ]),
            'resolved_folders' => [
                ['name' => '2.6 Programación', 'id' => 'folder-26'],
            ],
            'missing_folders' => [],
        ];
        $this->fakeDrive->recursiveResponses['02 Presupuesto|'] = [
            'folder_label' => '02 Presupuesto',
            'items' => collect([
                ['id' => 'excel-1', 'name' => 'Presupuesto Excel.xlsx', 'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                ['id' => 'linked-1', 'name' => '2.1 Resumen.pdf', 'mimeType' => 'application/pdf'],
                ['id' => 'linked-xlsx', 'name' => '2.1 Presupuesto Excel.xlsx', 'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                ['id' => 'mem-1', 'name' => '2.2 Memorias.pdf', 'mimeType' => 'application/pdf'],
                ['id' => 'apu-1', 'name' => '2.3 APUS Excel.xlsx', 'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                ['id' => 'loose-2', 'name' => '2.4 Mercado.pdf', 'mimeType' => 'application/pdf'],
                ['id' => 'spec-1', 'name' => '2.5 Especificaciones Tecnicas.pdf', 'mimeType' => 'application/pdf'],
                ['id' => 'loose-3', 'name' => '2.6 Programacion.pdf', 'mimeType' => 'application/pdf'],
                ['id' => 'ignored-4', 'name' => '2.6 Programacion.docx', 'mimeType' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            ]),
            'resolved_folders' => [
                ['name' => '02 Presupuesto', 'id' => 'folder-root'],
            ],
            'missing_folders' => [],
        ];

        $run = AttachmentPackageRun::query()->create([
            'project_id' => $project->id,
            'status' => 'running',
            'meta' => [],
        ]);

        $service = new AttachmentPackageService();
        $method = new \ReflectionMethod($service, 'buildDocumentsManifest');
        $method->setAccessible(true);

        $downloadDir = $this->testTempRoot() . '/' . uniqid('budget-', true);
        File::ensureDirectoryExists($downloadDir);

        $documents = $method->invoke($service, $project->fresh('requisitos'), $downloadDir, null, $run);

        $this->assertCount(1, $documents);
        $this->assertSame('2 Presupuesto CT', $documents[0]['title']);
        $this->assertCount(4, $documents[0]['files']);
        $this->assertSame(
            [
                'Presupuesto Excel.xlsx',
                '2.1 Presupuesto.pdf',
                '2.4 Estudio de Mercado.pdf',
                '2.6 Programación.pdf',
            ],
            collect($documents[0]['files'])->pluck('name')->values()->all()
        );
        $this->assertSame(
            [
                '02 Presupuesto',
                '2.1 Presupuesto',
                '2.4 Estudio de Mercado',
                '2.6 Programación',
            ],
            collect($documents[0]['files'])->pluck('folder_name')->values()->all()
        );
        $this->assertArrayNotHasKey('bundle_files', $documents[0]['files'][0]);
        $this->assertArrayHasKey('bundle_files', $documents[0]['files'][1]);
        $this->assertSame(
            ['2 1 Presupuesto base.pdf', '2 1 1 Presupuesto Excel.xlsx'],
            collect($documents[0]['files'][1]['bundle_files'])->pluck('name')->values()->all()
        );
        $this->assertSame(
            ['2 4 Estudio de Mercado.pdf', '2 4 2 Estudio de Mercado Excel.xlsx'],
            collect($documents[0]['files'][2]['bundle_files'])->pluck('name')->values()->all()
        );
        $this->assertSame(
            ['2 6 Programacion.pdf', '2 6 1 Programacion Project.mpp'],
            collect($documents[0]['files'][3]['bundle_files'])->pluck('name')->values()->all()
        );
        $this->assertSame(
            ['excel-1', 'linked-1', 'linked-xlsx', 'loose-2', 'market-xlsx', 'loose-3', 'project-mpp'],
            $this->fakeDrive->downloadedFileIds
        );
        $this->assertNotContains('excel-old', $this->fakeDrive->downloadedFileIds);
        $this->assertNotContains('mem-1', $this->fakeDrive->downloadedFileIds);
        $this->assertNotContains('apu-1', $this->fakeDrive->downloadedFileIds);
        $this->assertNotContains('spec-1', $this->fakeDrive->downloadedFileIds);
        $this->assertNotContains('ignored-4', $this->fakeDrive->downloadedFileIds);
        $this->assertNotContains('unrelated-1', $this->fakeDrive->downloadedFileIds);
        $this->assertNotContains('editable-1', $this->fakeDrive->downloadedFileIds);
        $this->assertCount(0, $this->fakeDrive->recursiveCalls);

        $trace = $run->fresh()->meta['manifest_trace'] ?? [];
        $this->assertCount(1, $trace);
        $this->assertSame(3, $trace[0]['recursive_files_added_count'] ?? null);
        $this->assertSame(0, $trace[0]['recursive_root_remainder_added_count'] ?? null);
        $this->assertCount(3, $trace[0]['recursive_bundles'] ?? []);
        $this->assertSame([2, 2, 2], collect($trace[0]['recursive_bundles'])->pluck('bundle_files_count')->values()->all());
    }

    public function test_sections_without_recursive_configuration_keep_existing_behavior(): void
    {
        $project = Project::query()->create([
            'nombre' => 'Proyecto Estudios',
            'drive_folder_id' => 'drive-root',
        ]);

        $parent = AttachmentPackageSection::query()->create([
            'name' => '05 Estudios y Disenos',
            'orden' => 50,
            'active' => true,
            'source_group_code' => '05',
            'match_type' => 'group',
        ]);

        AttachmentPackageSection::query()->create([
            'parent_id' => $parent->id,
            'name' => '05 Estudios y Disenos',
            'orden' => 10,
            'active' => true,
            'source_group_code' => '05',
            'match_type' => 'studies_subfolders',
        ]);

        $requirement = Requirement::query()->create([
            'codigo_interno' => '5.10',
            'orden' => '5.10',
            'nombre_documento' => 'Estudio hidráulico',
            'carpeta' => '5.10 Hidraulico',
            'visible' => true,
        ]);
        $project->requisitos()->attach($requirement->id);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'drive_file_id' => 'study-1',
            'drive_file_name' => '5.10 Hidraulico.pdf',
            'drive_mime_type' => 'application/pdf',
            'in_drive' => true,
        ]);

        $run = AttachmentPackageRun::query()->create([
            'project_id' => $project->id,
            'status' => 'running',
            'meta' => [],
        ]);

        $service = new AttachmentPackageService();
        $method = new \ReflectionMethod($service, 'buildDocumentsManifest');
        $method->setAccessible(true);

        $downloadDir = $this->testTempRoot() . '/' . uniqid('study-', true);
        File::ensureDirectoryExists($downloadDir);

        $documents = $method->invoke($service, $project->fresh('requisitos'), $downloadDir, null, $run);

        $this->assertCount(1, $documents);
        $this->assertSame('5 01 5.10 Hidraulico', $documents[0]['title']);
        $this->assertCount(1, $documents[0]['files']);
        $this->assertSame(['study-1'], $this->fakeDrive->downloadedFileIds);
        $this->assertCount(0, $this->fakeDrive->recursiveCalls);
    }

    public function test_version_mode_can_keep_current_or_generate_next_version(): void
    {
        $this->fakeDrive->folderFiles = collect([
            ['name' => 'Adjuntos Carrera 23 V6.pdf'],
            ['name' => 'Adjuntos Carrera 23 V7.pdf'],
        ]);

        $service = new AttachmentPackageService();
        $method = new \ReflectionMethod($service, 'resolvePackageVersion');
        $method->setAccessible(true);

        $this->assertSame(7, $method->invoke($service, 'folder-output', null, 'current'));
        $this->assertSame(8, $method->invoke($service, 'folder-output', null, 'next'));
    }

    public function test_generate_and_upload_cleans_workdir_and_persists_drive_reference_without_local_paths(): void
    {
        $project = $this->seedSingleDocumentProject();

        AttachmentPackageSection::query()->create([
            'name' => '02 Presupuesto CT',
            'orden' => 10,
            'active' => true,
            'source_group_code' => '02',
            'match_type' => 'group_code',
        ]);

        $this->configureGeneratorScript([
            'pdf_filenames' => ['Presupuesto CT.pdf'],
            'missing_count' => 0,
        ]);

        $run = AttachmentPackageRun::query()->create([
            'project_id' => $project->id,
            'status' => 'running',
            'selected_documents' => ['section:1'],
            'meta' => [],
        ]);

        $service = new AttachmentPackageService();
        $result = $service->generateAndUpload($run);

        $this->assertSame('folder-02-cargue', $result->drive_folder_id);
        $this->assertSame('uploaded-file-1', $result->drive_file_id);
        $this->assertSame('pdf', $result->output_type);
        $this->assertSame('Presupuesto CT.pdf', $result->output_filename);
        $this->assertNull($result->output_local_path);
        $this->assertNull($result->zip_local_path);
        $this->assertFalse(is_dir($this->runWorkDir($run->id)));
        $this->assertCount(1, $this->fakeDrive->uploadedFiles);
        $this->assertSame('Presupuesto CT.pdf', $this->fakeDrive->uploadedFiles[0]['file_name']);
    }

    public function test_generate_and_upload_cleans_workdir_even_when_drive_upload_fails(): void
    {
        $project = $this->seedSingleDocumentProject();

        AttachmentPackageSection::query()->create([
            'name' => '02 Presupuesto CT',
            'orden' => 10,
            'active' => true,
            'source_group_code' => '02',
            'match_type' => 'group_code',
        ]);

        $this->configureGeneratorScript([
            'pdf_filenames' => ['Presupuesto CT.pdf'],
            'missing_count' => 0,
        ]);
        $this->fakeDrive->uploadShouldFail = true;

        $run = AttachmentPackageRun::query()->create([
            'project_id' => $project->id,
            'status' => 'running',
            'selected_documents' => ['section:1'],
            'meta' => [],
        ]);

        $service = new AttachmentPackageService();

        try {
            $service->generateAndUpload($run);
            $this->fail('Expected upload failure was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo simulado al subir a Drive.', $e->getMessage());
        }

        $this->assertFalse(is_dir($this->runWorkDir($run->id)));
    }

    public function test_download_uses_drive_file_when_local_copy_is_missing(): void
    {
        $project = Project::query()->create([
            'nombre' => 'Proyecto Descarga',
            'drive_folder_id' => 'drive-root',
        ]);

        $run = AttachmentPackageRun::query()->create([
            'project_id' => $project->id,
            'status' => 'success',
            'output_type' => 'pdf',
            'output_filename' => 'Adjuntos V1.pdf',
            'drive_file_id' => 'drive-output-1',
        ]);

        $response = app(AttachmentPackageRunController::class)->download($project, $run);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Adjuntos V1.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertSame(['drive-output-1'], $this->fakeDrive->downloadedFileIds);
        $this->assertStringStartsWith(realpath(sys_get_temp_dir()), realpath($response->getFile()->getPathname()) ?: $response->getFile()->getPathname());
    }

    private function testTempRoot(): string
    {
        return sys_get_temp_dir() . '/tests-attachment-package';
    }

    private function runWorkDir(int $runId): string
    {
        return storage_path('app/tmp/attachment-runs/' . $runId);
    }

    private function configureGeneratorScript(array $payload): void
    {
        $scriptPath = $this->testTempRoot() . '/fake_attachment_generator.php';
        File::ensureDirectoryExists(dirname($scriptPath));
        $payloadExport = var_export($payload, true);

        $script = <<<'PHP'
<?php
$manifestIndex = array_search('--manifest', $argv, true);
if ($manifestIndex === false || !isset($argv[$manifestIndex + 1])) {
    fwrite(STDERR, 'Manifest missing');
    exit(1);
}

$manifest = json_decode(file_get_contents($argv[$manifestIndex + 1]), true);
$payload = __PAYLOAD__;
$outputDir = $manifest['output_dir'] ?? sys_get_temp_dir();
$pdfFiles = $payload['pdf_filenames'] ?? ['salida.pdf'];

foreach ($pdfFiles as $filename) {
    file_put_contents($outputDir . '/' . $filename, 'pdf test content');
}

$result = [
    'pdf_filenames' => $pdfFiles,
    'missing_report' => null,
    'general_report' => null,
    'missing_count' => $payload['missing_count'] ?? 0,
];

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
PHP;
        $script = str_replace('__PAYLOAD__', $payloadExport, $script);

        file_put_contents($scriptPath, $script);

        config()->set('services.attachments_pdf.python_bin', 'php');
        config()->set('services.attachments_pdf.script_path', $scriptPath);
    }

    private function seedSingleDocumentProject(): Project
    {
        $project = Project::query()->create([
            'nombre' => 'Proyecto Limpieza',
            'drive_folder_id' => 'drive-root',
        ]);

        $requirement = Requirement::query()->create([
            'codigo_interno' => '2.0',
            'orden' => '2.0',
            'nombre_documento' => 'Presupuesto PDF',
            'carpeta' => '02 Presupuesto',
            'visible' => true,
        ]);
        $project->requisitos()->attach($requirement->id);

        RequirementEvidence::query()->create([
            'project_id' => $project->id,
            'requirement_id' => $requirement->id,
            'drive_file_id' => 'evidence-1',
            'drive_file_name' => 'Presupuesto.pdf',
            'drive_mime_type' => 'application/pdf',
            'in_drive' => true,
        ]);

        return $project;
    }
}

class FakeAttachmentPackageDriveService extends GoogleDriveService
{
    public array $recursiveResponses = [];
    public array $recursiveCalls = [];
    public array $downloadedFileIds = [];
    public array $uploadedFiles = [];
    public bool $uploadShouldFail = false;
    public Collection $folderFiles;

    public function listFolderFiles(string $folderId, ?int $userId = null): Collection
    {
        return $this->folderFiles ?? collect();
    }

    public function listProjectSubfolderFiles(
        Project $project,
        string $rootFolderName,
        array $subfolderNames = [],
        ?int $userId = null,
        ?string $extension = null
    ): array {
        $key = $rootFolderName . '|' . implode('|', $subfolderNames);
        $this->recursiveCalls[] = [
            'project_id' => $project->id,
            'root' => $rootFolderName,
            'subfolders' => $subfolderNames,
            'extension' => $extension,
        ];

        return $this->recursiveResponses[$key] ?? [
            'folder_label' => $rootFolderName,
            'items' => collect(),
            'total' => 0,
            'resolved_folders' => [],
            'missing_folders' => $subfolderNames,
        ];
    }

    public function downloadFile(string $fileId, string $localPath, ?int $userId = null): void
    {
        File::ensureDirectoryExists(dirname($localPath));
        file_put_contents($localPath, 'fake-' . $fileId);
        $this->downloadedFileIds[] = $fileId;
    }

    public function ensureProjectSubfolder(Project $project, string $folderName, ?int $userId = null): ?string
    {
        return 'folder-02-cargue';
    }

    public function uploadLocalFileToFolder(
        string $folderId,
        string $fileName,
        string $localPath,
        string $mimeType = 'application/octet-stream',
        ?int $userId = null,
        ?callable $progressCallback = null
    ): array {
        if ($this->uploadShouldFail) {
            throw new \RuntimeException('Fallo simulado al subir a Drive.');
        }

        $size = (int) (filesize($localPath) ?: 0);
        if ($progressCallback) {
            $progressCallback($size, $size);
        }

        $this->uploadedFiles[] = [
            'folder_id' => $folderId,
            'file_name' => $fileName,
            'local_path' => $localPath,
            'mime_type' => $mimeType,
        ];

        return [
            'id' => 'uploaded-file-1',
            'name' => $fileName,
            'mimeType' => $mimeType,
        ];
    }
}
