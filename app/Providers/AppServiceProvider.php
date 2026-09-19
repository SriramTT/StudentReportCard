<?php

namespace App\Providers;

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
        \Illuminate\Database\Eloquent\Model::shouldBeStrict(! $this->app->isProduction());

        \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            $username = \Illuminate\Support\Str::lower((string) $request->input('username', ''));
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($username . '|' . $request->ip());
        });

        \Illuminate\Support\Facades\Gate::policy(\App\Models\GeneratedReport::class, \App\Policies\ReportPolicy::class);

        // Ensure meta schema exists for isolated migration repository tracking
        if ($this->app->runningInConsole()) {
            try {
                \Illuminate\Support\Facades\DB::statement('CREATE SCHEMA IF NOT EXISTS meta;');
            } catch (\Throwable $e) {
                // Ignore during early bootstrap or if connection unavailable
            }

            // Reset meta schema deterministically when db:wipe or migrate:fresh runs
            \Illuminate\Support\Facades\Event::listen(
                \Illuminate\Console\Events\CommandStarting::class,
                function (\Illuminate\Console\Events\CommandStarting $event) {
                    if (in_array($event->command, ['db:wipe', 'migrate:fresh'])) {
                        try {
                            \Illuminate\Support\Facades\DB::statement('DROP SCHEMA IF EXISTS meta CASCADE; CREATE SCHEMA IF NOT EXISTS meta;');
                        } catch (\Throwable $e) {
                            // Ignore if database connection unavailable
                        }
                    }
                }
            );
        }
    }
}
