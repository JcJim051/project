<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->must_change_password) {
            return $next($request);
        }

        $routeName = (string) optional($request->route())->getName();
        $allowedRoutes = [
            'filament.admin.auth.profile',
            'filament.admin.auth.logout',
            'default.livewire.update',
            'livewire.update',
            'livewire.message',
            'livewire.upload-file',
            'livewire.preview-file',
        ];

        if (in_array($routeName, $allowedRoutes, true)) {
            return $next($request);
        }

        if ($request->is('livewire/*')) {
            return $next($request);
        }

        return redirect()->route('filament.admin.auth.profile');
    }
}
