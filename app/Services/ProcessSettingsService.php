<?php

namespace App\Services;

use App\Models\ProcessSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class ProcessSettingsService
{
    private const CACHE_KEY = 'process_settings.active';

    public function requirePlanningAimApproval(): bool
    {
        $settings = $this->active();

        return (bool) ($settings['require_planning_aim_approval'] ?? false);
    }

    public function active(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addMinutes(5), function (): array {
            if (!Schema::hasTable('process_settings')) {
                return ['require_planning_aim_approval' => false];
            }

            $setting = ProcessSetting::query()->latest('id')->first();

            return [
                'require_planning_aim_approval' => (bool) ($setting?->require_planning_aim_approval ?? false),
            ];
        });
    }

    public function save(array $data, ?int $userId = null): ProcessSetting
    {
        $setting = ProcessSetting::query()->latest('id')->first() ?: new ProcessSetting();
        $setting->fill([
            'require_planning_aim_approval' => (bool) ($data['require_planning_aim_approval'] ?? false),
            'updated_by' => $userId,
        ]);
        $setting->save();

        Cache::forget(self::CACHE_KEY);

        return $setting;
    }
}
