<?php

namespace App\Providers;

use App\Models\Project;
use App\Models\PointActivity;
use App\Observers\PointActivityObserver;
use App\Observers\ProjectObserver;
use App\Services\MailSettingsService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Project::observe(ProjectObserver::class);
        PointActivity::observe(PointActivityObserver::class);
        app(MailSettingsService::class)->applyRuntimeConfig();
    }
}
