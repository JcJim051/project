<?php

namespace App\Http\Controllers;

use App\Models\TeamOnboardingCampaign;
use App\Services\TeamOnboardingService;
use Illuminate\Http\Request;

class TeamOnboardingPublicController extends Controller
{
    public function showCampaign(string $token, TeamOnboardingService $service)
    {
        $campaign = TeamOnboardingCampaign::query()
            ->where('public_token', $token)
            ->firstOrFail();

        return view('team-onboarding.public-campaign', [
            'campaign' => $campaign,
            'status' => $campaign->registration_status,
            'summary' => $service->buildCampaignSummary($campaign),
            'campaignUrl' => route('team-onboarding.campaign', $campaign->public_token),
            'registerUrl' => route('team-onboarding.register', $campaign->public_token),
            'qrDataUri' => $service->qrSvgDataUri(route('team-onboarding.register', $campaign->public_token)),
        ]);
    }

    public function showRegister(string $token, TeamOnboardingService $service)
    {
        $campaign = TeamOnboardingCampaign::query()
            ->where('public_token', $token)
            ->firstOrFail();

        return view('team-onboarding.public-register', [
            'campaign' => $campaign,
            'status' => $campaign->registration_status,
            'canSubmit' => $campaign->acceptsRegistrations(),
            'summary' => $service->buildCampaignSummary($campaign),
            'campaignUrl' => route('team-onboarding.campaign', $campaign->public_token),
        ]);
    }

    public function submit(string $token, Request $request, TeamOnboardingService $service)
    {
        $campaign = TeamOnboardingCampaign::query()
            ->where('public_token', $token)
            ->firstOrFail();

        $data = $request->validate([
            'requested_role' => ['required', 'in:formulador,estructurador,especialista'],
            'document_number' => ['required', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
        ], [
            'requested_role.required' => 'Debes seleccionar un rol.',
            'document_number.required' => 'El documento es obligatorio.',
            'full_name.required' => 'Los nombres y apellidos son obligatorios.',
            'email.required' => 'El correo es obligatorio.',
            'email.email' => 'Debes ingresar un correo válido.',
        ]);

        if (($data['requested_role'] ?? null) === 'especialista' && blank($data['specialty'] ?? null)) {
            return back()
                ->withInput()
                ->withErrors(['specialty' => 'La especialidad es obligatoria para especialistas.']);
        }

        try {
            $service->createRequest($campaign, $data);
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['team_onboarding' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Caracterización enviada correctamente. Quedó pendiente de revisión.',
            ]);
        }

        return redirect()
            ->route('team-onboarding.register', $token)
            ->with('status', 'Caracterización enviada correctamente. Quedó pendiente de revisión.');
    }
}
