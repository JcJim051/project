<?php

namespace App\Http\Controllers;

use App\Models\MeetingAttendanceSession;
use App\Services\MeetingAttendanceService;
use Illuminate\Http\Request;

class MeetingAttendancePublicController extends Controller
{
    public function showSession(string $token, MeetingAttendanceService $service)
    {
        $session = MeetingAttendanceSession::query()
            ->where('public_token', $token)
            ->firstOrFail();

        return view('attendance.public-session', [
            'session' => $session,
            'status' => $session->registration_status,
            'summary' => $service->buildSessionSummary($session),
            'sessionUrl' => route('attendance.session', $session->public_token),
            'registerUrl' => route('attendance.register', $session->public_token),
            'qrDataUri' => $service->qrSvgDataUri(route('attendance.register', $session->public_token)),
        ]);
    }

    public function showRegister(string $token, MeetingAttendanceService $service)
    {
        $session = MeetingAttendanceSession::query()
            ->where('public_token', $token)
            ->firstOrFail();

        return view('attendance.public-register', [
            'session' => $session,
            'status' => $session->registration_status,
            'canSubmit' => $session->acceptsRegistrations(),
            'summary' => $service->buildSessionSummary($session),
            'sessionUrl' => route('attendance.session', $session->public_token),
        ]);
    }

    public function summary(string $token, MeetingAttendanceService $service)
    {
        $session = MeetingAttendanceSession::query()
            ->where('public_token', $token)
            ->firstOrFail();

        return response()->json($service->buildSessionSummary($session));
    }

    public function downloadXlsx(string $token, MeetingAttendanceService $service)
    {
        $session = MeetingAttendanceSession::query()
            ->where('public_token', $token)
            ->firstOrFail();

        $path = $service->buildOfficialXlsx($session);

        return response()
            ->download($path, 'registro_asistencia_' . $session->id . '.xlsx')
            ->deleteFileAfterSend(true);
    }

    public function downloadPdf(string $token, MeetingAttendanceService $service)
    {
        $session = MeetingAttendanceSession::query()
            ->where('public_token', $token)
            ->firstOrFail();

        $path = $service->buildOfficialPdf($session);

        return response()
            ->download($path, 'registro_asistencia_' . $session->id . '.pdf')
            ->deleteFileAfterSend(true);
    }

    public function submit(string $token, Request $request, MeetingAttendanceService $service)
    {
        $session = MeetingAttendanceSession::query()
            ->where('public_token', $token)
            ->firstOrFail();

        $data = $request->validate([
            'document_number' => ['required', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'organization_area' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:100'],
            'email_or_address' => ['nullable', 'string', 'max:255'],
            'signature_data' => ['required', 'string'],
        ], [
            'document_number.required' => 'El documento es obligatorio.',
            'full_name.required' => 'Los nombres y apellidos son obligatorios.',
            'signature_data.required' => 'La firma es obligatoria.',
        ]);

        try {
            $service->registerAttendance($session, $data);
        } catch (\RuntimeException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return back()
                ->withInput()
                ->withErrors(['attendance' => $e->getMessage()]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Asistencia registrada correctamente.',
            ]);
        }

        return redirect()
            ->route('attendance.register', $token)
            ->with('status', 'Asistencia registrada correctamente.');
    }
}
