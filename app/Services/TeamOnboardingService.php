<?php

namespace App\Services;

use App\Jobs\ProvisionPlaneUserInvitationJob;
use App\Jobs\ProvisionPlaneSpecialistInvitationJob;
use App\Models\Role;
use App\Models\Specialist;
use App\Models\TeamOnboardingCampaign;
use App\Models\TeamOnboardingRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamOnboardingService
{
    public function generatePublicToken(): string
    {
        do {
            $token = Str::random(40);
        } while (TeamOnboardingCampaign::query()->where('public_token', $token)->exists());

        return $token;
    }

    public function normalizeDocument(?string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $value);

        return $digits !== '' ? $digits : null;
    }

    public function qrSvgDataUri(string $url): string
    {
        return app(MeetingAttendanceService::class)->qrSvgDataUri($url);
    }

    public function buildCampaignSummary(TeamOnboardingCampaign $campaign): array
    {
        $requests = $campaign->requests()->get();

        return [
            'id' => $campaign->id,
            'status' => $campaign->registration_status,
            'count' => $requests->count(),
            'pending' => $requests->where('status', 'pending')->count(),
            'approved' => $requests->where('status', 'approved')->count(),
            'rejected' => $requests->where('status', 'rejected')->count(),
            'requests' => $requests->map(fn (TeamOnboardingRequest $request): array => [
                'id' => $request->id,
                'full_name' => $request->full_name,
                'requested_role' => $request->requestedRoleLabel(),
                'status' => $request->statusLabel(),
                'submitted_at' => optional($request->submitted_at)->toDateTimeString(),
            ])->all(),
        ];
    }

    public function createRequest(TeamOnboardingCampaign $campaign, array $data): TeamOnboardingRequest
    {
        if (!$campaign->acceptsRegistrations()) {
            throw new \RuntimeException('Esta campaña no se encuentra disponible para recibir caracterizaciones.');
        }

        $role = (string) ($data['requested_role'] ?? '');
        if (!in_array($role, ['formulador', 'estructurador', 'especialista'], true)) {
            throw new \RuntimeException('Debes seleccionar un rol válido.');
        }

        $normalizedDocument = $this->normalizeDocument((string) ($data['document_number'] ?? ''));
        if (!$normalizedDocument) {
            throw new \RuntimeException('El documento es obligatorio.');
        }

        return DB::transaction(function () use ($campaign, $data, $role, $normalizedDocument): TeamOnboardingRequest {
            $existing = TeamOnboardingRequest::query()
                ->where('campaign_id', $campaign->id)
                ->where('document_number_normalized', $normalizedDocument)
                ->whereIn('status', ['pending', 'approved'])
                ->exists();

            if ($existing) {
                throw new \RuntimeException('Ya existe una caracterización vigente para este documento en esta campaña.');
            }

            return TeamOnboardingRequest::query()->create([
                'campaign_id' => $campaign->id,
                'requested_role' => $role,
                'document_number' => trim((string) ($data['document_number'] ?? '')),
                'document_number_normalized' => $normalizedDocument,
                'full_name' => trim((string) ($data['full_name'] ?? '')),
                'phone' => trim((string) ($data['phone'] ?? '')) ?: null,
                'email' => trim((string) ($data['email'] ?? '')) ?: null,
                'municipio' => trim((string) ($data['municipio'] ?? '')) ?: null,
                'organization_area' => trim((string) ($data['organization_area'] ?? '')) ?: null,
                'specialty' => $role === 'especialista' ? (trim((string) ($data['specialty'] ?? '')) ?: null) : null,
                'notes' => $role === 'especialista' ? (trim((string) ($data['notes'] ?? '')) ?: null) : null,
                'status' => 'pending',
                'submitted_at' => now(),
            ]);
        });
    }

    public function approveRequest(TeamOnboardingRequest $request, User $reviewer): TeamOnboardingRequest
    {
        if ($request->status !== 'pending') {
            throw new \RuntimeException('Solo se pueden aprobar solicitudes pendientes.');
        }

        return DB::transaction(function () use ($request, $reviewer): TeamOnboardingRequest {
            if ($request->requested_role === 'especialista') {
                $specialist = $this->createSpecialistFromRequest($request);
                $request->forceFill([
                    'status' => 'approved',
                    'approved_user_id' => $reviewer->id,
                    'approved_at' => now(),
                    'review_notes' => null,
                    'created_specialist_id' => $specialist->id,
                ])->save();

                DB::afterCommit(function () use ($specialist): void {
                    ProvisionPlaneSpecialistInvitationJob::dispatch($specialist->id);
                });

                return $request->fresh(['createdSpecialist', 'approvedBy', 'campaign']);
            }

            $user = $this->createUserFromRequest($request);
            $request->forceFill([
                'status' => 'approved',
                'approved_user_id' => $reviewer->id,
                'approved_at' => now(),
                'review_notes' => null,
                'created_user_id' => $user->id,
            ])->save();

            DB::afterCommit(function () use ($user): void {
                app(UserOnboardingService::class)->sendWelcomeEmail($user);
                ProvisionPlaneUserInvitationJob::dispatch($user->id);
            });

            return $request->fresh(['createdUser', 'approvedBy', 'campaign']);
        });
    }

    public function rejectRequest(TeamOnboardingRequest $request, User $reviewer, ?string $notes = null): TeamOnboardingRequest
    {
        if ($request->status !== 'pending') {
            throw new \RuntimeException('Solo se pueden rechazar solicitudes pendientes.');
        }

        $request->forceFill([
            'status' => 'rejected',
            'rejected_user_id' => $reviewer->id,
            'rejected_at' => now(),
            'review_notes' => trim((string) $notes) ?: null,
        ])->save();

        return $request->fresh(['rejectedBy', 'campaign']);
    }

    private function createUserFromRequest(TeamOnboardingRequest $request): User
    {
        $roleSlug = $request->requested_role;
        $role = Role::query()->where('slug', $roleSlug)->first();
        if (!$role) {
            throw new \RuntimeException('No existe el rol configurado para crear este usuario.');
        }

        $duplicateUser = User::query()
            ->where(function ($query) use ($request): void {
                $query->where('documento', $request->document_number_normalized);

                if ($request->email) {
                    $query->orWhere('email', $request->email);
                }
            })
            ->exists();

        if ($duplicateUser) {
            throw new \RuntimeException('Ya existe un usuario con ese documento o correo.');
        }

        $passwordSeed = $request->document_number_normalized ?: $request->document_number;
        $user = User::query()->create([
            'name' => $request->full_name,
            'email' => (string) $request->email,
            'documento' => $request->document_number_normalized ?: $request->document_number,
            'password' => Hash::make($passwordSeed),
            'is_admin' => false,
            'must_change_password' => true,
            'plane_sync_status' => 'pending',
            'plane_last_error' => null,
        ]);

        $user->roles()->sync([$role->id]);

        return $user->fresh('roles');
    }

    private function createSpecialistFromRequest(TeamOnboardingRequest $request): Specialist
    {
        $duplicateSpecialist = Specialist::query()
            ->where(function ($query) use ($request): void {
                $query->where('documento', $request->document_number_normalized);

                if ($request->email) {
                    $query->orWhere('correo', $request->email);
                }
            })
            ->exists();

        if ($duplicateSpecialist) {
            throw new \RuntimeException('Ya existe un especialista con ese documento o correo.');
        }

        return Specialist::query()->create([
            'nombre' => $request->full_name,
            'correo' => $request->email,
            'documento' => $request->document_number_normalized ?: $request->document_number,
            'telefono' => $request->phone,
            'especialidad' => $request->specialty,
            'notas' => $request->notes,
            'activo' => true,
            'plane_sync_status' => 'pending',
            'plane_last_error' => null,
        ]);
    }
}
