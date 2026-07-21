<?php

namespace App\Http\Controllers;

use App\Models\MeetingAttendanceSession;
use App\Services\MeetingAttendanceService;

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

        $path = $service->buildOfficialXlsx($session);

        return response()->download($path, 'registro_asistencia_' . $session->id . '.xlsx')->deleteFileAfterSend(true);
    }

    public function downloadPdf(MeetingAttendanceSession $session, MeetingAttendanceService $service)
    {
        $this->authorizeAccess();

        $path = $service->buildOfficialPdf($session);

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
