<?php

namespace Tests\Feature;

use App\Models\MeetingAttendanceSession;
use App\Models\MeetingAttendanceEntry;
use App\Models\MeetingPerson;
use App\Models\ProfesionalAmbiental;
use App\Models\Specialist;
use App\Models\User;
use App\Services\MeetingAttendanceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MeetingAttendanceModuleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('documento')->nullable();
            $table->string('password')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->boolean('must_change_password')->default(false);
            $table->timestamps();
        });

        Schema::create('specialists', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->nullable();
            $table->string('documento')->nullable();
            $table->timestamps();
        });

        Schema::create('profesionales_ambientales', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->nullable();
            $table->string('telefono')->nullable();
            $table->string('documento')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('meeting_attendance_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('public_token', 80)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('objetivo')->nullable();
            $table->date('fecha')->nullable();
            $table->string('lugar')->nullable();
            $table->time('hora_inicio')->nullable();
            $table->time('hora_terminacion')->nullable();
            $table->string('template_version')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_people', function (Blueprint $table): void {
            $table->id();
            $table->string('document_number')->nullable();
            $table->string('document_number_normalized')->nullable()->unique();
            $table->string('full_name');
            $table->string('organization_area')->nullable();
            $table->string('phone')->nullable();
            $table->string('email_or_address')->nullable();
            $table->string('person_kind')->default('external');
            $table->string('internal_source_type')->nullable();
            $table->unsignedBigInteger('internal_source_id')->nullable();
            $table->timestamps();
        });

        Schema::create('meeting_attendance_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('session_id');
            $table->unsignedBigInteger('person_id');
            $table->string('document_number')->nullable();
            $table->string('document_number_normalized')->nullable();
            $table->string('full_name');
            $table->string('organization_area')->nullable();
            $table->string('phone')->nullable();
            $table->string('email_or_address')->nullable();
            $table->string('signature_path')->nullable();
            $table->unsignedInteger('sequence_number')->default(1);
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('meeting_attendance_entries');
        Schema::dropIfExists('meeting_people');
        Schema::dropIfExists('meeting_attendance_sessions');
        Schema::dropIfExists('profesionales_ambientales');
        Schema::dropIfExists('specialists');
        Schema::dropIfExists('users');
        parent::tearDown();
    }

    public function test_public_form_blocks_expired_token(): void
    {
        $session = MeetingAttendanceSession::query()->create([
            'public_token' => 'expired-token',
            'is_active' => true,
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->get(route('attendance.form', $session->public_token));

        $response->assertOk();
        $response->assertSee('no se encuentra disponible', false);
    }

    public function test_submit_creates_internal_person_and_attendance_entry(): void
    {
        User::query()->create([
            'name' => 'Ana Interna',
            'email' => 'ana@example.com',
            'documento' => '1030590916',
        ]);

        $session = MeetingAttendanceSession::query()->create([
            'public_token' => 'active-token',
            'is_active' => true,
            'objetivo' => 'Comité técnico',
        ]);

        $response = $this->post(route('attendance.submit', $session->public_token), [
            'document_number' => '1.030.590.916',
            'full_name' => 'Ana Interna',
            'organization_area' => 'Dirección AIM',
            'phone' => '3105550101',
            'email_or_address' => 'ana@example.com',
            'signature_data' => 'data:image/png;base64,' . base64_encode('png'),
        ]);

        $response->assertRedirect(route('attendance.form', $session->public_token));
        $person = MeetingPerson::query()->first();
        $this->assertNotNull($person);
        $this->assertSame('1030590916', $person->document_number_normalized);
        $this->assertSame('internal', $person->person_kind);
        $this->assertSame('user', $person->internal_source_type);
        $this->assertSame(1, MeetingAttendanceEntry::query()->count());
    }

    public function test_submit_blocks_duplicate_document_in_same_session(): void
    {
        $session = MeetingAttendanceSession::query()->create([
            'public_token' => 'dup-token',
            'is_active' => true,
        ]);

        $payload = [
            'document_number' => '1030590916',
            'full_name' => 'Persona Duplicada',
            'organization_area' => 'Entidad',
            'phone' => '3105550101',
            'email_or_address' => 'persona@example.com',
            'signature_data' => 'data:image/png;base64,' . base64_encode('png'),
        ];

        $this->post(route('attendance.submit', $session->public_token), $payload);
        $response = $this->post(route('attendance.submit', $session->public_token), $payload);

        $response->assertSessionHasErrors();
        $this->assertSame(1, MeetingAttendanceEntry::query()->count());
    }
}
