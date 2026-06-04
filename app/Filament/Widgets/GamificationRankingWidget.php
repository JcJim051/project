<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\GamificationService;
use Filament\Widgets\Widget;

class GamificationRankingWidget extends Widget
{
    protected static string $view = 'filament.widgets.gamification-ranking-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;


    public static function canView(): bool
    {
        $user = auth()->user();

        return (bool) ($user && !$user->isPlanningAimOnlyUser());
    }
    protected function getViewData(): array
    {
        $year = (int) now()->year;
        /** @var GamificationService $service */
        $service = app(GamificationService::class);
        $user = auth()->user();

        $myProgress = [
            'level' => 'Recluta UNSC',
            'role_label' => 'Usuario',
            'points' => 0,
            'next' => 'Nivel máximo',
        ];

        if ($user) {
            $points = $service->score((int) $user->id, $year);
            $level = $service->levelForPoints($points);
            $roleLabel = $user->hasRole('estructurador')
                ? 'Estructurador'
                : ($user->hasRole('formulador') ? 'Formulador' : 'Usuario');
            $myProgress = [
                'level' => (string) ($level['name'] ?? 'Recluta UNSC'),
                'role_label' => $roleLabel,
                'points' => (int) $points,
                'next' => !empty($level['next_name'])
                    ? ($level['next_name'] . ' (' . (int) $level['next_min_points'] . ' pts)')
                    : 'Nivel máximo',
            ];
        }

        $rows = $service->ranking($year, ['formulador', 'estructurador'])
            ->take(10)
            ->values()
            ->map(function ($row, $index) use ($service) {
                $user = User::find((int) $row->user_id);
                $points = (int) $row->points;
                $level = $service->levelForPoints($points);

                return [
                    'position' => $index + 1,
                    'user_name' => $user?->name ?: 'Usuario',
                    'points' => $points,
                    'level' => (string) ($level['name'] ?? 'Nivel'),
                ];
            });

        return [
            'year' => $year,
            'rows' => $rows,
            'myProgress' => $myProgress,
        ];
    }
}
