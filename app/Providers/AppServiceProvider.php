<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Policies\CustomerPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Policies\StorePolicy;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\Facades\Gate;
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
            if (isset($_SERVER['DOCUMENT_ROOT']) && ! empty($_SERVER['DOCUMENT_ROOT'])) {
                $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'], '/');
                if (file_exists($docRoot.'/build/manifest.json') || file_exists($docRoot.'/build/.vite/manifest.json')) {
                    return $docRoot;
                }
            }

            // 2. Auto-detect paths in user's home directory
            $baseDir = dirname($this->app->basePath());
            $possiblePaths = [
                $baseDir.'/pos.sababilling.net',
                $baseDir.'/public_html/pos.sababilling.net',
                $baseDir.'/pos',
                $baseDir.'/public_html',
                $baseDir.'/public_html/sabapos',
            ];

            foreach ($possiblePaths as $path) {
                if (file_exists($path.'/build/manifest.json') || file_exists($path.'/build/.vite/manifest.json')) {
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
        Gate::policy(Store::class, StorePolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        $rawAppUrl = (string) (config('app.url') ?? '');

        // Enforce HTTPS scheme on production or when behind SSL / Reverse Proxies (cPanel, Nginx, Cloudflare)
        $isHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            app()->environment('production') ||
            str_starts_with($rawAppUrl, 'https://');

        if ($isHttps) {
            URL::forceScheme('https');
        }

        if ($appUrl = config('app.url')) {
            if (! app()->environment('production') && ! empty($_SERVER['HTTP_HOST'])) {
                $reqHost = parse_url($_SERVER['HTTP_HOST'], PHP_URL_HOST) ?? $_SERVER['HTTP_HOST'];
                $reqHost = preg_replace('/:\d+$/', '', $reqHost);
                if (in_array($reqHost, ['localhost', '127.0.0.1'], true)) {
                    $scheme = (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                    $appUrl = $scheme.'://'.$_SERVER['HTTP_HOST'];
                }
            }
            URL::forceRootUrl($appUrl);
        }

        if (class_exists(AboutCommand::class)) {
            AboutCommand::add('Queue Security', fn () => [
                'Driver' => config('queue.default'),
                'Status' => (config('app.env') === 'production' && config('queue.default') === 'sync')
                    ? '⚠️ WARNING: QUEUE_CONNECTION=sync in production'
                    : 'OK (Async Queue Driver Active)',
            ]);
        }
    }
}
