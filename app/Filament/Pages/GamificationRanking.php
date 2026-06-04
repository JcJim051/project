<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Models\UserPointEvent;
use App\Services\GamificationService;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;

class GamificationRanking extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationGroup = 'Operacion';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Ranking';

    protected static ?string $navigationLabel = 'Ranking';

    protected static ?string $slug = 'ranking';

    protected static string $view = 'filament.pages.gamification-ranking';

    public int $seasonYear;

    public function mount(): void
    {
        $this->seasonYear = (int) now()->year;
    }

    public function getYearOptionsProperty(): array
    {
        $years = UserPointEvent::query()
            ->select('season_year')
            ->distinct()
            ->orderByDesc('season_year')
            ->pluck('season_year')
            ->map(fn ($y) => (int) $y)
            ->all();

        if (!in_array((int) now()->year, $years, true)) {
            array_unshift($years, (int) now()->year);
        }

        return collect($years)->unique()->sortDesc()->values()->mapWithKeys(fn ($y) => [$y => (string) $y])->all();
    }

    public function getRankingProperty()
    {
        $service = app(GamificationService::class);
        $rows = UserPointEvent::query()
            ->selectRaw('user_id, SUM(points) as points, MAX(awarded_at) as last_awarded_at')
            ->where('season_year', $this->seasonYear)
            ->groupBy('user_id')
            ->orderByDesc('points')
            ->orderBy('last_awarded_at')
            ->limit(50)
            ->get();

        $users = User::query()->with('roles')->whereIn('id', $rows->pluck('user_id'))->get()->keyBy('id');

        return $rows->map(function ($row, $idx) use ($users, $service) {
            $user = $users->get((int) $row->user_id);
            $points = (int) $row->points;
            $level = $service->levelForPoints($points);
            $role = $user?->hasRole('formulador') ? 'Formulador' : ($user?->hasRole('estructurador') ? 'Estructurador' : 'Otro');

            return [
                'pos' => $idx + 1,
                'name' => $user?->name ?? ('Usuario #' . $row->user_id),
                'role' => $role,
                'points' => $points,
                'level' => $level['name'],
                'level_image_url' => !empty($level['image_path']) ? Storage::disk('public')->url($level['image_path']) : null,
            ];
        });
    }

    public function getMyProgressProperty(): array
    {
        $user = auth()->user();
        if (!$user) {
            return ['points' => 0, 'level' => 'Recluta UNSC', 'next' => null];
        }

        $service = app(GamificationService::class);
        $points = $service->score((int) $user->id, (int) $this->seasonYear);
        $level = $service->levelForPoints($points);

        return [
            'points' => $points,
            'level' => $level['name'],
            'level_image_url' => !empty($level['image_path']) ? Storage::disk('public')->url($level['image_path']) : null,
            'next' => $level['next_name'] ? ($level['next_name'] . ' (' . $level['next_min_points'] . ' pts)') : 'Nivel máximo',
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return (bool) ($user && $user->canAccessPanel() && !$user->isPlanningAimOnlyUser());
    }
}
