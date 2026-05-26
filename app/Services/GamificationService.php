<?php

namespace App\Services;

use App\Models\PointActivity;
use App\Models\PointRank;
use App\Models\User;
use App\Models\UserPointEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GamificationService
{
    public function award(string $activityCode, int $userId, array $context = [], ?Carbon $at = null): ?UserPointEvent
    {
        $user = User::query()->with('roles')->find($userId);
        if (!$user) {
            return null;
        }

        $activity = PointActivity::query()
            ->where('code', $activityCode)
            ->where('enabled', true)
            ->first();

        if (!$activity) {
            return null;
        }

        if (!$this->roleAllowed($activity->role_scope, $user)) {
            return null;
        }

        $awardedAt = $at ?: now();
        $today = $awardedAt->toDateString();
        if ($activity->effective_from && $today < $activity->effective_from->toDateString()) {
            return null;
        }
        if ($activity->effective_to && $today > $activity->effective_to->toDateString()) {
            return null;
        }

        $seasonYear = (int) $awardedAt->year;
        $eventKey = $this->buildEventKey(
            (string) $activity->uniqueness_scope,
            $seasonYear,
            (int) ($context['project_id'] ?? 0),
            (int) ($context['requirement_id'] ?? 0),
            $awardedAt
        );

        return DB::transaction(function () use ($activity, $userId, $context, $awardedAt, $seasonYear, $eventKey) {
            $exists = UserPointEvent::query()
                ->where('user_id', $userId)
                ->where('season_year', $seasonYear)
                ->where('activity_code', $activity->code)
                ->where('event_key', $eventKey)
                ->exists();

            if ($exists) {
                return null;
            }

            return UserPointEvent::query()->create([
                'user_id' => $userId,
                'project_id' => $context['project_id'] ?? null,
                'requirement_id' => $context['requirement_id'] ?? null,
                'point_activity_id' => $activity->id,
                'activity_code' => $activity->code,
                'activity_name' => $activity->name,
                'points' => (int) $activity->points,
                'season_year' => $seasonYear,
                'awarded_at' => $awardedAt,
                'uniqueness_scope' => $activity->uniqueness_scope,
                'event_key' => $eventKey,
                'metadata' => $context['metadata'] ?? null,
            ]);
        });
    }

    public function score(int $userId, int $seasonYear): int
    {
        return (int) UserPointEvent::query()
            ->where('user_id', $userId)
            ->where('season_year', $seasonYear)
            ->sum('points');
    }

    public function ranking(int $seasonYear, ?array $roleSlugs = null)
    {
        $query = UserPointEvent::query()
            ->selectRaw('user_id, SUM(points) as points, MAX(awarded_at) as last_awarded_at')
            ->where('season_year', $seasonYear)
            ->groupBy('user_id');

        if (!empty($roleSlugs)) {
            $query->whereHas('user.roles', fn ($q) => $q->whereIn('slug', $roleSlugs));
        }

        return $query->orderByDesc('points')->orderBy('last_awarded_at')->get();
    }

    public function levelForPoints(int $points): array
    {
        $levels = PointRank::query()
            ->where('enabled', true)
            ->orderBy('min_points')
            ->get(['name', 'min_points', 'image_path'])
            ->map(fn ($rank) => [
                'name' => (string) $rank->name,
                'min_points' => (int) $rank->min_points,
                'image_path' => $rank->image_path,
            ])
            ->values();

        if ($levels->isEmpty()) {
            $levels = collect(config('gamification.levels', []))->sortBy('min_points')->values();
        }

        $current = $levels->first();
        $next = null;
        foreach ($levels as $idx => $level) {
            if ($points >= (int) $level['min_points']) {
                $current = $level;
                $next = $levels[$idx + 1] ?? null;
            }
        }

        return [
            'name' => $current['name'] ?? 'Recluta UNSC',
            'min_points' => (int) ($current['min_points'] ?? 0),
            'image_path' => $current['image_path'] ?? null,
            'next_name' => $next['name'] ?? null,
            'next_min_points' => $next ? (int) $next['min_points'] : null,
            'next_image_path' => $next['image_path'] ?? null,
        ];
    }

    private function roleAllowed(string $roleScope, User $user): bool
    {
        if ($roleScope === 'ambos') {
            return $user->hasAnyRole(['formulador', 'estructurador']);
        }
        if ($roleScope === 'formulador') {
            return $user->hasRole('formulador');
        }
        if ($roleScope === 'estructurador') {
            return $user->hasRole('estructurador');
        }
        return false;
    }

    private function buildEventKey(string $scope, int $seasonYear, int $projectId, int $requirementId, Carbon $awardedAt): string
    {
        return match ($scope) {
            'once_per_requirement' => 'r:' . ($requirementId > 0 ? $requirementId : '0'),
            'once_per_project' => 'p:' . ($projectId > 0 ? $projectId : '0'),
            'once_per_day' => 'd:' . $awardedAt->toDateString(),
            default => 's:' . $seasonYear,
        };
    }
}
