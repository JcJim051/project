<?php

namespace Tests\Unit;

use App\Models\OperationalState;
use App\Models\OperationalCycle;
use App\Jobs\ProvisionPlaneProjectJob;
use App\Models\PlaneConnection;
use App\Models\Project;
use App\Services\OperationalActivityMappingService;
use App\Services\PlaneProvisioningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlaneStateProvisioningTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        app('db')->purge('sqlite');
        app('db')->reconnect('sqlite');

        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->timestamp('plane_states_seeded_at')->nullable();
            $table->timestamps();
        });
        Schema::create('operational_states', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo')->unique();
            $table->string('nombre');
            $table->unsignedInteger('orden');
            $table->string('color')->nullable();
            $table->boolean('activo')->default(true);
            $table->boolean('es_final')->default(false);
            $table->boolean('es_bloqueante')->default(false);
            $table->string('equivalente_plane')->nullable();
            $table->timestamps();
        });

        $this->seedCatalog();
    }

    public function test_new_project_replaces_native_states_with_orbit_catalog_without_duplicates(): void
    {
        $project = Project::query()->create();
        $states = $this->nativeStates();
        $writes = 0;
        $this->fakePlaneStates($states, $writes);

        $result = $this->invokeSyncStates($project);

        $this->assertCount(6, $result);
        $this->assertSame([], $result->whereNull('external_source')->values()->all());
        $this->assertSame(
            ['Pendiente', 'En ejecución', 'En revisión', 'Ajustes', 'Completado', 'Bloqueado'],
            $result->sortBy('sequence')->pluck('name')->values()->all()
        );
        $this->assertTrue((bool) $result->firstWhere('name', 'Pendiente')['default']);
        $this->assertNotNull($project->fresh()->plane_states_seeded_at);
        $this->assertGreaterThan(0, $writes);

        $writesBeforeRetry = $writes;
        $this->invokeSyncStates($project->fresh());
        $this->assertSame($writesBeforeRetry, $writes, 'A completed seed must not write states again.');
    }

    public function test_existing_project_marked_as_seeded_is_not_modified_when_orbit_states_are_complete(): void
    {
        $project = Project::query()->create(['plane_states_seeded_at' => now()]);
        $states = [
            ['id' => 'orbit-pendiente', 'name' => 'Pendiente', 'group' => 'backlog', 'sequence' => 10000, 'default' => true, 'external_source' => 'orbit', 'external_id' => '1'],
            ['id' => 'orbit-ejecucion', 'name' => 'En ejecución', 'group' => 'started', 'sequence' => 20000, 'default' => false, 'external_source' => 'orbit', 'external_id' => '2'],
            ['id' => 'orbit-revision', 'name' => 'En revisión', 'group' => 'started', 'sequence' => 30000, 'default' => false, 'external_source' => 'orbit', 'external_id' => '3'],
            ['id' => 'orbit-ajustes', 'name' => 'Ajustes', 'group' => 'unstarted', 'sequence' => 40000, 'default' => false, 'external_source' => 'orbit', 'external_id' => '4'],
            ['id' => 'orbit-completado', 'name' => 'Completado', 'group' => 'completed', 'sequence' => 50000, 'default' => false, 'external_source' => 'orbit', 'external_id' => '5'],
            ['id' => 'orbit-bloqueado', 'name' => 'Bloqueado', 'group' => 'cancelled', 'sequence' => 60000, 'default' => false, 'external_source' => 'orbit', 'external_id' => '6'],
        ];
        $writes = 0;
        $this->fakePlaneStates($states, $writes);

        $result = $this->invokeSyncStates($project);

        $this->assertCount(6, $result);
        $this->assertSame(0, $writes);
    }

    public function test_existing_project_marked_as_seeded_is_repaired_when_native_states_remain(): void
    {
        $project = Project::query()->create(['plane_states_seeded_at' => now()]);
        $states = $this->nativeStates();
        $writes = 0;
        $this->fakePlaneStates($states, $writes);

        $result = $this->invokeSyncStates($project);

        $this->assertCount(6, $result);
        $this->assertSame([], $result->whereNull('external_source')->values()->all());
        $this->assertGreaterThan(0, $writes);
    }

    public function test_plane_identifier_is_unique_alphanumeric_and_at_most_twelve_characters(): void
    {
        $service = new PlaneProvisioningService(app(OperationalActivityMappingService::class));
        $method = new \ReflectionMethod($service, 'buildPlaneIdentifier');
        $method->setAccessible(true);
        $project = new Project(['id_proyecto' => 'QA-STATE-20260630123456']);
        $project->id = 12345;

        $identifier = $method->invoke($service, $project);

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{1,12}$/', $identifier);
        $this->assertLessThanOrEqual(12, strlen($identifier));
        $this->assertStringEndsWith(strtoupper(base_convert('12345', 10, 36)), $identifier);
    }

    public function test_relative_cycle_dates_are_calculated_from_project_creation(): void
    {
        $service = new PlaneProvisioningService(app(OperationalActivityMappingService::class));
        $method = new \ReflectionMethod($service, 'resolveCycleDates');
        $method->setAccessible(true);
        $project = new Project();
        $project->created_at = '2026-07-01 10:00:00';
        $cycle = new OperationalCycle([
            'nombre' => 'Corte 2',
            'anchor_type' => 'project_created_at',
            'start_offset_days' => 14,
            'duration_days' => 14,
        ]);

        [$start, $end] = $method->invoke($service, $project, $cycle);

        $this->assertSame('2026-07-15', $start);
        $this->assertSame('2026-07-28', $end);
    }

    public function test_activation_rule_only_tracks_activation_and_does_not_set_start_date(): void
    {
        $service = new PlaneProvisioningService(app(OperationalActivityMappingService::class));
        $method = new \ReflectionMethod($service, 'withBlueprintDates');
        $method->setAccessible(true);
        $project = new Project();
        $project->created_at = '2026-07-01 10:00:00';

        $result = $method->invoke(
            $service,
            $project,
            collect([
                'dedupe_key' => 'generic:1',
                'requirement_id' => null,
                'planned_start_rule' => 'activation',
                'start_offset_days' => 10,
                'default_duration_days' => 5,
                'mapping_created_at' => '2026-07-01 10:00:00',
            ]),
            collect(),
            collect(),
            collect()
        );

        $this->assertNotNull($result['activated_at']);
        $this->assertNull($result['planned_start_date']);
        $this->assertNull($result['planned_target_date']);
    }

    public function test_issue_payload_includes_dates_labels_and_keeps_state_optional(): void
    {
        $service = new PlaneProvisioningService(app(OperationalActivityMappingService::class));
        $method = new \ReflectionMethod($service, 'issuePayloadVariants');
        $method->setAccessible(true);
        $variants = $method->invoke(
            $service,
            'Revisar diseño',
            'Validar entregable',
            null,
            'module-1',
            'requirement:10',
            true,
            'high',
            ['user-1'],
            '2026-07-02',
            '2026-07-10',
            ['label-1']
        );

        $this->assertNotEmpty($variants);
        $this->assertSame('2026-07-02', $variants[0]['start_date']);
        $this->assertSame('2026-07-10', $variants[0]['target_date']);
        $this->assertSame(['label-1'], $variants[0]['labels']);
        $this->assertArrayNotHasKey('state', $variants[0]);
    }

    public function test_requirement_tasks_resolve_to_completed_only_when_valid_evidence_exists(): void
    {
        $service = new PlaneProvisioningService(app(OperationalActivityMappingService::class));
        $method = new \ReflectionMethod($service, 'desiredPlaneStateCode');
        $method->setAccessible(true);

        $this->assertSame('completado', $method->invoke($service, [
            'source_type' => 'requirement',
            'has_valid_evidence' => true,
        ]));

        $this->assertSame('pendiente', $method->invoke($service, [
            'source_type' => 'requirement',
            'has_valid_evidence' => false,
        ]));

        $this->assertNull($method->invoke($service, [
            'source_type' => 'generic',
            'has_valid_evidence' => true,
        ]));
    }

    public function test_plane_job_limits_prevent_timeout_and_duplicate_processing(): void
    {
        $job = new ProvisionPlaneProjectJob(12);

        $this->assertSame(900, $job->timeout);
        $this->assertSame(1800, $job->uniqueFor);
        $this->assertSame(1200, config('queue.connections.database.retry_after'));
        $this->assertGreaterThan($job->timeout, config('queue.connections.database.retry_after'));
    }

    public function test_legacy_plane_job_without_sync_run_id_remains_compatible(): void
    {
        $job = (new \ReflectionClass(ProvisionPlaneProjectJob::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($job, 'syncRun');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($job));
    }

    public function test_partial_seed_is_recovered_and_does_not_duplicate_pending_state(): void
    {
        $project = Project::query()->create();
        $pending = OperationalState::query()->where('codigo', 'pendiente')->firstOrFail();
        $states = collect($this->nativeStates())->push([
            'id' => 'orbit-partial-pending',
            'name' => 'Pendiente',
            'group' => 'backlog',
            'sequence' => 70000,
            'default' => false,
            'external_source' => 'orbit',
            'external_id' => (string) $pending->id,
        ])->all();
        $writes = 0;
        $this->fakePlaneStates($states, $writes);

        $result = $this->invokeSyncStates($project);

        $this->assertCount(6, $result);
        $this->assertCount(1, $result->where('name', 'Pendiente'));
        $this->assertTrue((bool) $result->firstWhere('name', 'Pendiente')['default']);
    }

    private function invokeSyncStates(Project $project)
    {
        $service = new PlaneProvisioningService(app(OperationalActivityMappingService::class));
        $method = new \ReflectionMethod($service, 'syncStates');
        $method->setAccessible(true);
        $connection = new PlaneConnection([
            'url_base' => 'https://plane.test',
            'workspace_id' => 'orbit',
            'states_path_template' => '/api/v1/workspaces/{workspace_slug}/projects/{project_id}/states/',
        ]);

        return $method->invoke(
            $service,
            Http::acceptJson()->asJson(),
            $connection,
            'plane-project-1',
            $project
        );
    }

    private function fakePlaneStates(array &$states, int &$writes): void
    {
        Http::fake(function (Request $request) use (&$states, &$writes) {
            if ($request->method() === 'GET') {
                return Http::response(['results' => array_values($states)], 200);
            }

            $writes++;
            $payload = $request->data();
            $stateId = basename(rtrim($request->url(), '/'));

            if ($request->method() === 'POST') {
                if (($payload['default'] ?? false) === true) {
                    foreach ($states as &$state) {
                        $state['default'] = false;
                    }
                    unset($state);
                }

                $payload['id'] = 'orbit-' . ($payload['external_id'] ?? count($states));
                $states[] = $payload;

                return Http::response($payload, 200);
            }

            if ($request->method() === 'PATCH') {
                foreach ($states as &$state) {
                    if ($state['id'] === $stateId) {
                        $state = array_merge($state, $payload);
                    }
                }
                unset($state);

                return Http::response([], 200);
            }

            if ($request->method() === 'DELETE') {
                $states = array_values(array_filter($states, fn (array $state): bool => $state['id'] !== $stateId));

                return Http::response([], 204);
            }

            return Http::response([], 405);
        });
    }

    private function nativeStates(): array
    {
        return [
            ['id' => 'native-backlog', 'name' => 'Backlog', 'group' => 'backlog', 'sequence' => 15000, 'default' => true, 'external_source' => null, 'external_id' => null],
            ['id' => 'native-todo', 'name' => 'Todo', 'group' => 'unstarted', 'sequence' => 25000, 'default' => false, 'external_source' => null, 'external_id' => null],
            ['id' => 'native-progress', 'name' => 'In Progress', 'group' => 'started', 'sequence' => 35000, 'default' => false, 'external_source' => null, 'external_id' => null],
            ['id' => 'native-done', 'name' => 'Done', 'group' => 'completed', 'sequence' => 45000, 'default' => false, 'external_source' => null, 'external_id' => null],
            ['id' => 'native-cancelled', 'name' => 'Cancelled', 'group' => 'cancelled', 'sequence' => 55000, 'default' => false, 'external_source' => null, 'external_id' => null],
        ];
    }

    private function seedCatalog(): void
    {
        foreach ([
            ['pendiente', 'Pendiente', 1, '#9CA3AF', false, false],
            ['en_ejecucion', 'En ejecución', 2, '#3B82F6', false, false],
            ['en_revision', 'En revisión', 3, '#F59E0B', false, false],
            ['ajustes', 'Ajustes', 4, '#F97316', false, false],
            ['completado', 'Completado', 5, '#16A34A', true, false],
            ['bloqueado', 'Bloqueado', 6, '#DC2626', false, true],
        ] as [$codigo, $nombre, $orden, $color, $final, $blocked]) {
            OperationalState::query()->create([
                'codigo' => $codigo,
                'nombre' => $nombre,
                'orden' => $orden,
                'color' => $color,
                'activo' => true,
                'es_final' => $final,
                'es_bloqueante' => $blocked,
            ]);
        }
    }
}
