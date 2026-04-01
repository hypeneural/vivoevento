<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registers all domain module service providers from config/modules.php.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $modules = config('modules.modules', []);

        foreach ($modules as $name => $providerClass) {
            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    public function boot(): void
    {
        // Module providers handle their own boot logic
    }
}
