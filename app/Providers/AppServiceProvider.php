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
        $this->app->bind(
            \App\Services\Calculation\Contracts\CalculationParticipationResolverInterface::class,
            \App\Services\Calculation\Resolvers\TermExamParticipationResolver::class
        );

        $this->app->bind(
            \App\Contracts\ReportGeneratorContract::class,
            \App\Services\Report\Generators\BrowsershotReportGenerator::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Database\Eloquent\Model::shouldBeStrict(! $this->app->isProduction());

        \Illuminate\Support\Facades\RateLimiter::for('login', function (\Illuminate\Http\Request $request) {
            $username = $request->input('username');
            if (empty($username) && $request->hasSession()) {
                $challenge = $request->session()->get('auth.otp_challenge');
                $username = is_array($challenge) ? ($challenge['username'] ?? '') : '';
            }
            $normalized = \Illuminate\Support\Str::lower((string) ($username ?? ''));
            return \Illuminate\Cache\RateLimiting\Limit::perMinute(5)->by($normalized . '|' . $request->ip());
        });

        \Illuminate\Support\Facades\Gate::policy(\App\Models\GeneratedReport::class, \App\Policies\ReportPolicy::class);
        \Illuminate\Pagination\Paginator::defaultView('pagination.custom');
        \Illuminate\Pagination\Paginator::defaultSimpleView('pagination.custom');

        \Illuminate\Support\Facades\View::composer('layouts.app', function ($view) {
            try {
                $schoolSetting = \App\Models\SchoolSetting::first();
                $logoUrl = ($schoolSetting && $schoolSetting->school_logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($schoolSetting->school_logo_path))
                    ? asset('storage/' . $schoolSetting->school_logo_path)
                    : null;

                $view->with([
                    'schoolName' => $schoolSetting?->school_name ?: 'School System',
                    'schoolLogoUrl' => $logoUrl,
                ]);
            } catch (\Throwable $e) {
                $view->with([
                    'schoolName' => 'School System',
                    'schoolLogoUrl' => null,
                ]);
            }
        });

        \Illuminate\Support\Facades\View::composer(['auth.login', 'auth.otp', 'auth.setup'], function ($view) {
            try {
                $schoolSetting = \App\Models\SchoolSetting::first();
                $logoUrl = ($schoolSetting && $schoolSetting->school_logo_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($schoolSetting->school_logo_path))
                    ? asset('storage/' . $schoolSetting->school_logo_path)
                    : null;

                $view->with([
                    'schoolName' => ($schoolSetting && filled($schoolSetting->school_name)) ? $schoolSetting->school_name : null,
                    'schoolLogoUrl' => $logoUrl,
                ]);
            } catch (\Throwable $e) {
                $view->with([
                    'schoolName' => null,
                    'schoolLogoUrl' => null,
                ]);
            }
        });

        // Ensure meta schema exists for isolated migration repository tracking
        if ($this->app->runningInConsole()) {
            try {
                \Illuminate\Support\Facades\DB::statement('CREATE SCHEMA IF NOT EXISTS meta;');
            } catch (\Throwable $e) {
                // Ignore during early bootstrap or if connection unavailable
            }

            // Permanent Safety Guard: Prohibit destructive commands against persistent application database
            $activeDb = config('database.connections.pgsql.database');
            $disposableTestDatabases = ['school_report_card_audit', 'school_report_card_test'];

            if (! in_array($activeDb, $disposableTestDatabases, true)) {
                \Illuminate\Support\Facades\DB::prohibitDestructiveCommands(true);
            }

            // Fail-closed event listener preventing destructive operations on persistent databases
            \Illuminate\Support\Facades\Event::listen(
                \Illuminate\Console\Events\CommandStarting::class,
                function (\Illuminate\Console\Events\CommandStarting $event) use ($disposableTestDatabases) {
                    $destructiveCommands = ['db:wipe', 'migrate:fresh', 'migrate:reset', 'migrate:rollback', 'migrate:refresh'];
                    
                    if (in_array($event->command, $destructiveCommands, true)) {
                        $currentDb = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
                        if (! in_array($currentDb, $disposableTestDatabases, true)) {
                            throw new \RuntimeException(
                                "DESTRUCTIVE COMMAND BLOCKED: 'php artisan {$event->command}' is strictly forbidden against persistent application database '{$currentDb}'. " .
                                "Destructive operations are only permitted against dedicated disposable test databases (e.g., 'school_report_card_audit')."
                            );
                        }
                    }

                    // Reset meta schema deterministically when db:wipe or migrate:fresh runs on allowed test databases
                    if (in_array($event->command, ['db:wipe', 'migrate:fresh'], true)) {
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
