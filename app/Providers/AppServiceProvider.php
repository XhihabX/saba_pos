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
        $this->app->bind('path.public', function () {
            $baseDir = dirname($this->app->basePath());
            $possiblePaths = [
                $baseDir . '/public_html/pos',
                $baseDir . '/pos.sababilling.net',
                $baseDir . '/pos',
                $baseDir . '/public_html',
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path . '/build/manifest.json')) {
                    return $path;
                }
            }

            return $this->app->basePath('public');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (request()->isSecure() || app()->environment('production')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Auto-Migration Resilience Guard for cPanel & Shared Hosting
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('users') && !\Illuminate\Support\Facades\Schema::hasColumn('users', 'deleted_at')) {
                \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
            }
        } catch (\Throwable $e) {
            // Ignore error if DB connection is not initialized
        }
    }
}
