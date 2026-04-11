<?php

namespace App\Modules\EventPeople\Providers;

use App\Modules\EventPeople\Models\EventPerson;
use App\Modules\EventPeople\Policies\EventPersonPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class EventPeopleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(EventPerson::class, EventPersonPolicy::class);

        RateLimiter::for('event-people-aws-sync', function (): Limit {
            return Limit::perMinute((int) config('event_people.aws_sync_rate_limit_per_minute', 30))
                ->by('event-people-aws-sync');
        });

        $routeFile = __DIR__ . '/../routes/api.php';

        if (file_exists($routeFile)) {
            Route::prefix(config('modules.api_prefix') . '/' . config('modules.api_version'))
                ->middleware(['api'])
                ->group($routeFile);
        }
    }
}
