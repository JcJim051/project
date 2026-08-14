<?php

namespace App\Http\Controllers;

use App\Models\MeetingAttendanceSession;
use App\Services\MeetingAttendanceService;
use Illuminate\Support\Facades\Log;

class MeetingAttendanceSessionController extends Controller
{
    public function summary(MeetingAttendanceSession $session, MeetingAttendanceService $service)
    {
        $this->authorizeAccess();

        return response()->json($service->buildSessionSummary($session));
    }

    public function downloadXlsx(MeetingAttendanceSession $session, MeetingAttendanceService $service)
    {
        $this->authorizeAccess();

        try {
            $path = $service->buildOfficialXlsx($session);
        } catch (\Throwable $exception) {
            Log::error('No se pudo generar el XLSX de asistencia desde panel.', [
                'session_id' => $session->id,
                'message' => $exception->getMessage(),
            ]);

            abort(503, 'No se pudo generar el archivo XLSX de asistencia en este momento.');
        }

        return response()->download($path, 'registro_asistencia_' . $session->id . '.xlsx')->deleteFileAfterSend(true);
    }

    public function downloadPdf(MeetingAttendanceSession $session, MeetingAttendanceService $service)
    {
        $this->authorizeAccess();

        try {
            $path = $service->buildOfficialPdf($session);
        } catch (\Throwable $exception) {
            Log::error('No se pudo generar el PDF de asistencia desde panel.', [
                'session_id' => $session->id,
                'message' => $exception->getMessage(),
            ]);

            abort(503, 'No se pudo generar el archivo PDF de asistencia en este momento.');
        }

        return response()->download($path, 'registro_asistencia_' . $session->id . '.pdf')->deleteFileAfterSend(true);
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        if (!$user || !$user->canAccessPanel()) {
            abort(403);
        }
    }
}
