<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version and auto-syncs build assets for cPanel deployments.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        $this->ensureBuildAssetsSynced();

        return parent::version($request);
    }

    private function ensureBuildAssetsSynced(): void
    {
        try {
            $sourceManifest = base_path('public/build/manifest.json');
            if (! file_exists($sourceManifest)) {
                return;
            }

            $sourceMtime = filemtime($sourceManifest);
            $targetDirs = array_filter([
                dirname(base_path()).'/public_html/build',
                isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] ? rtrim($_SERVER['DOCUMENT_ROOT'], '/').'/build' : null,
            ]);

            foreach ($targetDirs as $dest) {
                if (! $dest || $dest === base_path('public/build')) {
                    continue;
                }
                $destManifest = $dest.'/manifest.json';
                if (! file_exists($destManifest) || filemtime($destManifest) < $sourceMtime) {
                    $this->copyDirRecursive(base_path('public/build'), $dest);
                    @touch($destManifest, $sourceMtime);
                    @Artisan::call('view:clear');
                    @Artisan::call('config:clear');
                }
            }
        } catch (\Throwable $e) {
            // Quiet fallback
        }
    }

    private function copyDirRecursive(string $src, string $dst): void
    {
        if (! file_exists($src)) {
            return;
        }
        if (! file_exists($dst)) {
            @mkdir($dst, 0755, true);
        }
        $dir = @opendir($src);
        if (! $dir) {
            return;
        }
        while (($file = readdir($dir)) !== false) {
            if ($file !== '.' && $file !== '..') {
                $srcPath = $src.'/'.$file;
                $dstPath = $dst.'/'.$file;
                if (is_dir($srcPath)) {
                    $this->copyDirRecursive($srcPath, $dstPath);
                } else {
                    @copy($srcPath, $dstPath);
                }
            }
        }
        closedir($dir);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role,
                    'tenant_id' => $request->user()->tenant_id,
                    'store_id' => $request->user()->store_id,
                    'tenant' => $request->user()->tenant ? [
                        'id' => $request->user()->tenant->id,
                        'name' => $request->user()->tenant->name,
                        'subscription_status' => $request->user()->tenant->subscription_status,
                    ] : null,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
