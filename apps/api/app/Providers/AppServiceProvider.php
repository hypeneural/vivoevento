<?php

namespace App\Providers;

use App\Modules\Users\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\QueueBusy;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Events\LongWaitDetected;

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
        Gate::define('viewHorizon', fn (?User $user = null): bool => $this->canAccessOperationalDashboards($user));
        Gate::define('viewTelescope', fn (?User $user = null): bool => $this->canAccessOperationalDashboards($user));
        Gate::define('viewPulse', fn (?User $user = null): bool => $this->canAccessOperationalDashboards($user));

        RateLimiter::for('public-face-search', function (Request $request) {
            $event = (string) $request->route('event', 'event');
            $key = sprintf('public-face-search:%s:%s', $event, $request->ip() ?: 'guest');

            return Limit::perMinute(10)->by($key);
        });

        Event::listen(LongWaitDetected::class, function (LongWaitDetected $event): void {
            Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                ->warning('queue.long_wait_detected', [
                    'connection' => $event->connection,
                    'queue' => $event->queue,
                    'wait' => $event->wait,
                ]);
        });

        Event::listen(QueueBusy::class, function (QueueBusy $event): void {
            Log::channel((string) config('observability.queue_log_channel', config('logging.default')))
                ->warning('queue.busy', [
                    'connection' => $event->connectionName,
                    'queue' => $event->queue,
                    'size' => $event->size,
                    'threshold' => config("observability.queue_busy_thresholds.{$event->queue}"),
                ]);
        });

        Queue::before(function (JobProcessing $event): void {
            $payload = $event->job->payload();

            Context::add([
                'queue_connection' => $event->connectionName,
                'queue_name' => $event->job->getQueue(),
                'job_name' => $event->job->resolveName(),
                'job_uuid' => $payload['uuid'] ?? null,
            ]);
        });
    }

    private function canAccessOperationalDashboards(?User $user): bool
    {
        if ($this->app->environment('local')) {
            return true;
        }

        if (! $user) {
            return false;
        }

        $roles = config('observability.operations_dashboard_roles', ['super-admin', 'platform-admin']);
        $permission = trim((string) config('observability.operations_dashboard_permission', 'audit.view'));

        return $user->hasAnyRole($roles)
            || ($permission !== '' && $user->can($permission));
    }
}
