<?php

namespace App\Services;

use App\Models\User;
use App\Notifications\UserWelcomeCredentialsNotification;
use Illuminate\Support\Facades\Log;

class UserOnboardingService
{
    public function sendWelcomeEmail(User $user): bool
    {
        try {
            if (blank($user->email)) {
                return false;
            }

            $user->notify(new UserWelcomeCredentialsNotification());

            return true;
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar correo de onboarding de usuario.', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
