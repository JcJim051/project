<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveService;
use Illuminate\Http\Request;

class DriveAuthController extends Controller
{
    public function redirect(Request $request, GoogleDriveService $drive)
    {
        $userId = $request->user()?->id ?: session('drive_auth_user_id');
        $returnUrl = $request->query('return');

        if (!$drive->isConfigured()) {
            return redirect()->back()->withErrors(['drive' => 'Faltan las credenciales de Google Drive en el .env.']);
        }

        if ($userId) {
            session(['drive_auth_user_id' => $userId]);
        }

        $url = $drive->getAuthUrl($userId, $returnUrl);

        return redirect()->away($url);
    }

    public function callback(Request $request, GoogleDriveService $drive)
    {
        $userId = $request->user()?->id;
        $code = $request->query('code');

        if (!$code) {
            return redirect()->route('projects.index')->withErrors(['drive' => 'No se recibió el código de autorización.']);
        }

        $drive->handleCallback($code, $userId);

        $returnUrl = session('drive_return_url', route('projects.index'));
        session()->forget(['drive_return_url', 'drive_auth_user_id']);

        return redirect($returnUrl)->with('status', 'Drive conectado correctamente.');
    }
}
