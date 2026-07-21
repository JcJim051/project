<?php

namespace Tests\Feature;

use App\Jobs\ProvisionPlaneUserInvitationJob;
use App\Jobs\ProvisionPlaneSpecialistInvitationJob;
use App\Models\Role;
use App\Models\Specialist;
use App\Models\TeamOnboardingCampaign;
use App\Models\TeamOnboardingRequest;
use App\Models\User;
use App\Notifications\UserWelcomeCredentialsNotification;
use App\Services\TeamOnboardingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeamOnboardingModuleTest extends TestCase
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
            $table->string('name');
            $table->string('slug')->unique();
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

        Schema::create('team_onboarding_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('public_token', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('team_onboarding_requests', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->string('requested_role', 50);
            $table->string('document_number', 100);
            $table->string('document_number_normalized', 100)->nullable();
            $table->string('full_name');
            $table->string('phone', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('municipio', 255)->nullable();
            $table->string('organization_area', 255)->nullable();
            $table->string('specialty', 255)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('review_notes')->nullable();
            $table->unsignedBigInteger('approved_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_user_id')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->unsignedBigInteger('created_user_id')->nullable();
            $table->unsignedBigInteger('created_specialist_id')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('team_onboarding_requests');
        Schema::dropIfExists('team_onboarding_campaigns');
        Schema::dropIfExists('specialists');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_public_register_page_loads_without_login(): void
    {
        $campaign = TeamOnboardingCampaign::query()->create([
            'title' => 'Convocatoria julio',
            'public_token' => 'public-token-1',
            'is_active' => true,
        ]);

        $response = $this->get(route('team-onboarding.register', $campaign->public_token));

        $response->assertOk();
        $response->assertSee('Selecciona tu rol', false);
    }

    public function test_public_submit_creates_pending_request(): void
    {
        $campaign = TeamOnboardingCampaign::query()->create([
            'title' => 'Convocatoria julio',
            'public_token' => 'public-token-2',
            'is_active' => true,
        ]);

        $response = $this->post(route('team-onboarding.submit', $campaign->public_token), [
            'requested_role' => 'formulador',
            'document_number' => '1.030.590.916',
            'full_name' => 'Ana Pérez',
            'phone' => '3105550101',
            'email' => 'ana@example.com',
        ]);

        $response->assertRedirect(route('team-onboarding.register', $campaign->public_token));

        $request = TeamOnboardingRequest::query()->first();
        $this->assertNotNull($request);
        $this->assertSame('pending', $request->status);
        $this->assertSame('1030590916', $request->document_number_normalized);
    }

    public function test_public_submit_blocks_closed_campaign(): void
    {
        $campaign = TeamOnboardingCampaign::query()->create([
            'title' => 'Convocatoria cerrada',
            'public_token' => 'public-token-3',
            'is_active' => false,
        ]);

        $response = $this->from(route('team-onboarding.register', $campaign->public_token))
            ->post(route('team-onboarding.submit', $campaign->public_token), [
                'requested_role' => 'formulador',
                'document_number' => '1030590916',
                'full_name' => 'Ana Pérez',
                'email' => 'ana@example.com',
            ]);

        $response->assertRedirect(route('team-onboarding.register', $campaign->public_token));
        $response->assertSessionHasErrors('team_onboarding');
        $this->assertSame(0, TeamOnboardingRequest::query()->count());
    }

    public function test_approving_formulador_creates_user_with_role_and_forced_password_change(): void
    {
        Queue::fake();
        Notification::fake();

        Role::query()->create(['name' => 'Formulador', 'slug' => 'formulador']);

        $reviewer = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => 'secret',
            'is_admin' => true,
        ]);

        $campaign = TeamOnboardingCampaign::query()->create([
            'title' => 'Convocatoria',
            'public_token' => 'public-token-4',
            'is_active' => true,
        ]);

        $request = TeamOnboardingRequest::query()->create([
            'campaign_id' => $campaign->id,
            'requested_role' => 'formulador',
            'document_number' => '1030590916',
            'document_number_normalized' => '1030590916',
            'full_name' => 'Ana Pérez',
            'email' => 'ana@example.com',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $approved = app(TeamOnboardingService::class)->approveRequest($request, $reviewer);

        $this->assertSame('approved', $approved->status);
        $createdUser = User::query()->where('email', 'ana@example.com')->first();
        $this->assertNotNull($createdUser);
        $this->assertTrue($createdUser->must_change_password);
        $this->assertSame('1030590916', $createdUser->documento);
        $this->assertSame('pending', $createdUser->plane_sync_status);
        $this->assertTrue(Hash::check('1030590916', (string) $createdUser->password));
        $this->assertSame(['formulador'], $createdUser->roles()->pluck('slug')->all());
        Notification::assertSentTo($createdUser, UserWelcomeCredentialsNotification::class);
        Queue::assertPushed(ProvisionPlaneUserInvitationJob::class, fn (ProvisionPlaneUserInvitationJob $job): bool => true);
    }

    public function test_approving_especialista_creates_specialist_record(): void
    {
        Queue::fake();

        $reviewer = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin2@example.com',
            'password' => 'secret',
            'is_admin' => true,
        ]);

        $campaign = TeamOnboardingCampaign::query()->create([
            'title' => 'Convocatoria especialistas',
            'public_token' => 'public-token-5',
            'is_active' => true,
        ]);

        $request = TeamOnboardingRequest::query()->create([
            'campaign_id' => $campaign->id,
            'requested_role' => 'especialista',
            'document_number' => '1030590917',
            'document_number_normalized' => '1030590917',
            'full_name' => 'Carlos Suárez',
            'phone' => '3201112233',
            'email' => 'carlos@example.com',
            'specialty' => 'Hidráulica',
            'notes' => 'Experiencia en diseño.',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $approved = app(TeamOnboardingService::class)->approveRequest($request, $reviewer);

        $this->assertSame('approved', $approved->status);
        $specialist = Specialist::query()->where('correo', 'carlos@example.com')->first();
        $this->assertNotNull($specialist);
        $this->assertSame('Hidráulica', $specialist->especialidad);
        $this->assertTrue($specialist->activo);
        $this->assertSame('pending', $specialist->plane_sync_status);
        Queue::assertPushed(ProvisionPlaneSpecialistInvitationJob::class, fn (ProvisionPlaneSpecialistInvitationJob $job): bool => true);
    }

    public function test_approval_blocks_duplicate_user_by_document_or_email(): void
    {
        Role::query()->create(['name' => 'Estructurador', 'slug' => 'estructurador']);

        $reviewer = User::query()->create([
            'name' => 'Administrador',
            'email' => 'admin3@example.com',
            'password' => 'secret',
            'is_admin' => true,
        ]);

        User::query()->create([
            'name' => 'Usuario existente',
            'email' => 'existente@example.com',
            'documento' => '1030590918',
            'password' => 'secret',
        ]);

        $campaign = TeamOnboardingCampaign::query()->create([
            'title' => 'Convocatoria duplicados',
            'public_token' => 'public-token-6',
            'is_active' => true,
        ]);

        $request = TeamOnboardingRequest::query()->create([
            'campaign_id' => $campaign->id,
            'requested_role' => 'estructurador',
            'document_number' => '1030590918',
            'document_number_normalized' => '1030590918',
            'full_name' => 'Persona nueva',
            'email' => 'otra@example.com',
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Ya existe un usuario con ese documento o correo.');

        app(TeamOnboardingService::class)->approveRequest($request, $reviewer);
    }
}
