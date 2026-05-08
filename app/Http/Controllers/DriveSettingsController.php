<?php

namespace App\Http\Controllers;

use App\Models\DriveOAuthSetting;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DriveSettingsController extends Controller
{
    public function edit(GoogleDriveService $driveService)
    {
        $user = auth()->user();
        abort_unless($user && $user->isAdminUser(), 403);

        $setting = DriveOAuthSetting::query()->latest('id')->first();
        $active = $driveService->oauthCredentials();

        return view('drive.settings', [
            'setting' => $setting,
            'active' => $active,
        ]);
    }

    public function update(Request $request, GoogleDriveService $driveService)
    {
        $user = auth()->user();
        abort_unless($user && $user->isAdminUser(), 403);

        $data = $request->validate([
            'client_id' => ['required', 'string', 'max:4000'],
            'client_secret' => ['required', 'string', 'max:4000'],
            'redirect_uri' => ['required', 'url', 'max:1000'],
        ], [
            'client_id.required' => 'El Client ID es obligatorio.',
            'client_secret.required' => 'El Client Secret es obligatorio.',
            'redirect_uri.required' => 'La Redirect URI es obligatoria.',
            'redirect_uri.url' => 'La Redirect URI debe ser una URL valida.',
        ]);

        $setting = DriveOAuthSetting::query()->latest('id')->first() ?: new DriveOAuthSetting();
        $setting->fill($data);
        $setting->updated_by = auth()->id();
        $setting->save();

        $driveService->forgetCredentialCache();
        $this->forgetAllDriveTokens();

        return redirect()
            ->route('drive.settings.edit')
            ->with('status', 'Credenciales de Drive actualizadas. Reconecta la cuenta para continuar.');
    }

    private function forgetAllDriveTokens(): void
    {
        $disk = Storage::disk('local');
        foreach ($disk->files() as $file) {
            if (str_starts_with($file, 'google-drive-token') && str_ends_with($file, '.json')) {
                $disk->delete($file);
            }
        }
    }
}
