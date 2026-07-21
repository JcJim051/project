<?php

namespace Tests\Feature;

use App\Models\PlaneConnection;
use App\Models\Specialist;
use App\Models\User;
use App\Services\PlaneProvisioningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlaneUserInvitationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('documento')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('must_change_password')->default(false);
            $table->string('plane_user_id')->nullable();
            $table->string('plane_sync_status')->nullable();
            $table->timestamp('plane_last_synced_at')->nullable();
            $table->text('plane_last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
        });

        Schema::create('specialists', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->nullable();
            $table->string('documento')->nullable();
            $table->string('telefono')->nullable();
            $table->string('especialidad')->nullable();
            $table->text('notas')->nullable();
            $table->boolean('activo')->default(true);
            $table->string('plane_user_id')->nullable();
            $table->string('plane_sync_status')->nullable();
            $table->timestamp('plane_last_synced_at')->nullable();
            $table->text('plane_last_error')->nullable();
            $table->timestamps();
        });

        Schema::create('plane_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre')->nullable();
            $table->string('entorno')->nullable();
            $table->string('url_base');
            $table->string('workspace_id')->nullable();
            $table->string('auth_type')->default('bearer_token');
            $table->string('oauth_token_url')->nullable();
            $table->string('healthcheck_path')->nullable();
            $table->string('projects_path')->nullable();
            $table->string('modules_path_template')->nullable();
            $table->string('states_path_template')->nullable();
            $table->string('labels_path_template')->nullable();
            $table->string('cycles_path_template')->nullable();
            $table->string('cycle_issues_path_template')->nullable();
            $table->string('issues_path_template')->nullable();
            $table->string('issue_detail_path_template')->nullable();
            $table->string('project_url_template')->nullable();
            $table->string('api_key_header')->nullable();
            $table->string('api_secret_header')->nullable();
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->boolean('activo')->default(false);
            $table->unsignedInteger('timeout_segundos')->default(15);
            $table->timestamp('ultima_prueba_at')->nullable();
            $table->string('ultimo_estado_prueba')->nullable();
            $table->text('ultimo_mensaje_prueba')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('plane_connections');
        Schema::dropIfExists('specialists');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_invite_user_to_plane_marks_user_as_linked_when_member_exists_after_invitation(): void
    {
        PlaneConnection::query()->create([
            'nombre' => 'Plane',
            'url_base' => 'https://plane.test',
            'workspace_id' => 'orbit',
            'auth_type' => 'bearer_token',
            'access_token' => 'token',
            'activo' => true,
            'timeout_segundos' => 5,
        ]);

        $user = User::query()->create([
            'name' => 'Ana Pérez',
            'email' => 'ana@example.com',
        ]);

        Http::fake([
            'https://plane.test/api/v1/workspaces/orbit/members/' => Http::sequence()
                ->push(['results' => []], 200)
                ->push([
                    'results' => [[
                        'email' => 'ana@example.com',
                        'member' => [
                            'id' => 'plane-user-1',
                            'display_name' => 'Ana Pérez',
                        ],
                    ]],
                ], 200),
            'https://plane.test/api/v1/workspaces/orbit/invitations/' => Http::response(['ok' => true], 201),
        ]);

        $result = app(PlaneProvisioningService::class)->inviteUserToWorkspace($user);

        $this->assertTrue($result['success']);
        $this->assertSame('linked', $result['status']);

        $user->refresh();
        $this->assertSame('linked', $user->plane_sync_status);
        $this->assertSame('plane-user-1', $user->plane_user_id);
        $this->assertNull($user->plane_last_error);
    }

    public function test_invite_user_to_plane_marks_user_as_invited_when_conflict_occurs_and_member_is_not_yet_visible(): void
    {
        PlaneConnection::query()->create([
            'nombre' => 'Plane',
            'url_base' => 'https://plane.test',
            'workspace_id' => 'orbit',
            'auth_type' => 'bearer_token',
            'access_token' => 'token',
            'activo' => true,
            'timeout_segundos' => 5,
        ]);

        $user = User::query()->create([
            'name' => 'Luis Pérez',
            'email' => 'luis@example.com',
        ]);

        Http::fake([
            'https://plane.test/api/v1/workspaces/orbit/members/' => Http::sequence()
                ->push(['results' => []], 200)
                ->push(['results' => []], 200),
            'https://plane.test/api/v1/workspaces/orbit/invitations/' => Http::response(['detail' => 'already invited'], 409),
        ]);

        $result = app(PlaneProvisioningService::class)->inviteUserToWorkspace($user);

        $this->assertTrue($result['success']);
        $this->assertSame('invited', $result['status']);

        $user->refresh();
        $this->assertSame('invited', $user->plane_sync_status);
        $this->assertNull($user->plane_user_id);
        $this->assertNotNull($user->plane_last_error);
    }

    public function test_invite_user_to_plane_marks_error_when_no_active_connection_exists(): void
    {
        $user = User::query()->create([
            'name' => 'Sin Plane',
            'email' => 'sinplane@example.com',
        ]);

        $result = app(PlaneProvisioningService::class)->inviteUserToWorkspace($user);

        $this->assertFalse($result['success']);
        $this->assertSame('missing_connection', $result['status']);

        $user->refresh();
        $this->assertSame('error', $user->plane_sync_status);
        $this->assertNotNull($user->plane_last_error);
    }

    public function test_invite_specialist_to_plane_marks_specialist_as_linked_when_member_exists_after_invitation(): void
    {
        PlaneConnection::query()->create([
            'nombre' => 'Plane',
            'url_base' => 'https://plane.test',
            'workspace_id' => 'orbit',
            'auth_type' => 'bearer_token',
            'access_token' => 'token',
            'activo' => true,
            'timeout_segundos' => 5,
        ]);

        $specialist = Specialist::query()->create([
            'nombre' => 'Laura Gómez',
            'correo' => 'laura@example.com',
            'activo' => true,
        ]);

        Http::fake([
            'https://plane.test/api/v1/workspaces/orbit/members/' => Http::sequence()
                ->push(['results' => []], 200)
                ->push([
                    'results' => [[
                        'email' => 'laura@example.com',
                        'member' => [
                            'id' => 'plane-user-2',
                            'display_name' => 'Laura Gómez',
                        ],
                    ]],
                ], 200),
            'https://plane.test/api/v1/workspaces/orbit/invitations/' => Http::response(['ok' => true], 201),
        ]);

        $result = app(PlaneProvisioningService::class)->inviteSpecialistToWorkspace($specialist);

        $this->assertTrue($result['success']);
        $this->assertSame('linked', $result['status']);

        $specialist->refresh();
        $this->assertSame('linked', $specialist->plane_sync_status);
        $this->assertSame('plane-user-2', $specialist->plane_user_id);
        $this->assertNull($specialist->plane_last_error);
    }
}
