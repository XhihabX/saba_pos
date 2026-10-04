<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind('path.public', function () {
            // 1. Check DOCUMENT_ROOT from web server (cPanel / Apache / Nginx)
            if (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
                $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
                if (file_exists($docRoot . '/build/manifest.json') || file_exists($docRoot . '/build/.vite/manifest.json')) {
                    return $docRoot;
                }
            }

            // 2. Auto-detect paths in user's home directory
            $baseDir = dirname($this->app->basePath());
            $possiblePaths = [
                $baseDir . '/public_html',
                $baseDir . '/sabapos',
                $baseDir . '/xhihab.com',
                $baseDir . '/public_html/sabapos',
                $baseDir . '/pos.sababilling.net',
                $baseDir . '/pos',
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path . '/build/manifest.json') || file_exists($path . '/build/.vite/manifest.json')) {
                    return $path;
                }
            }

            // 3. Fallback to default application public folder
            return $this->app->basePath('public');
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $rawAppUrl = (string) env('APP_URL', '');

        // Enforce HTTPS scheme on production or when behind SSL / Reverse Proxies (cPanel, Nginx, Cloudflare)
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            app()->environment('production') ||
            str_starts_with($rawAppUrl, 'https://');

        if ($isHttps) {
            URL::forceScheme('https');
        }

        // Hardened auto-healing for APP_URL to prevent broken asset URLs or malformed Ziggy routes (e.g. http://:)
        $host = $_SERVER['HTTP_HOST'] ?? 'xhihab.com';
        if (str_contains($host, ':') && !preg_match('/:\d+$/', $host)) {
            $host = preg_replace('/:.*$/', '', $host);
        }
        if (empty($host)) {
            $host = 'xhihab.com';
        }

        $scheme = $isHttps ? 'https' : 'http';
        $targetUrl = $scheme . '://' . $host;

        $currentConfigUrl = (string) config('app.url');
        if (empty($currentConfigUrl) || $currentConfigUrl === 'http://localhost' || str_contains($currentConfigUrl, 'http://:') || str_contains($currentConfigUrl, '://:')) {
            config(['app.url' => $targetUrl]);
        }

        URL::forceRootUrl(config('app.url') ?: $targetUrl);
    }
}


