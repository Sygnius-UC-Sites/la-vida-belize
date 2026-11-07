<?php

namespace App\Modules;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

/**
 * Base service provider for all PMS modules.
 *
 * Each module's ServiceProvider extends this and sets:
 *   - $moduleName (e.g. 'partner-referral')
 *   - $moduleNamespace (e.g. 'App\\Modules\\PartnerReferral')
 *
 * This base handles:
 *   - Loading routes from the module's routes/web.php under admin/<name> prefix.
 *   - Registering migrations from the module's database/migrations/ folder.
 *   - Loading Blade views with namespace = module name.
 *   - Loading translations from resources/lang/.
 *   - Publishing public assets if module.json sets publishesAssets = true.
 */
abstract class AbstractModuleServiceProvider extends ServiceProvider
{
    /**
     * Module name as used in folder + module.json. Lowercase-dashed.
     */
    protected string $moduleName;

    /**
     * Fully-qualified PHP namespace for the module (e.g. App\Modules\PartnerReferral).
     */
    protected string $moduleNamespace;

    /**
     * Absolute path to the module's root folder. Resolved from $moduleNamespace
     * by replacing namespace separators with directory separators.
     */
    protected function modulePath(): string
    {
        $relative = str_replace('App\\Modules\\', '', $this->moduleNamespace);
        $relative = str_replace('\\', DIRECTORY_SEPARATOR, $relative);
        return app_path('Modules' . DIRECTORY_SEPARATOR . $relative);
    }

    public function register(): void
    {
        // Modules can override and add their own bindings.
    }

    public function boot(): void
    {
        $base = $this->modulePath();

        // Admin routes — auto-wrapped in admin/<module-name> prefix with auth middleware.
        $routeFile = $base . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'web.php';
        if (file_exists($routeFile)) {
            Route::group([
                'prefix' => 'admin/' . $this->moduleName,
                'middleware' => ['web', 'auth'],
                'namespace' => $this->moduleNamespace . '\\Http\\Controllers',
                'as' => 'admin.' . $this->moduleName . '.',
            ], function () use ($routeFile) {
                require $routeFile;
            });
        }

        // Public routes (optional).
        $publicRouteFile = $base . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'public.php';
        if (file_exists($publicRouteFile)) {
            Route::group([
                'prefix' => $this->moduleName,
                'middleware' => ['web'],
                'namespace' => $this->moduleNamespace . '\\Http\\Controllers',
                'as' => $this->moduleName . '.',
            ], function () use ($publicRouteFile) {
                require $publicRouteFile;
            });
        }

        // Migrations.
        $migrationsPath = $base . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations';
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }

        // Views — accessible as `view('<module-name>::some-view')`.
        $viewsPath = $base . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'views';
        if (is_dir($viewsPath)) {
            $this->loadViewsFrom($viewsPath, $this->moduleName);
        }

        // Translations.
        $langPath = $base . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'lang';
        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleName);
        }

        // Assets — publishable to public/modules/<name>/.
        $publicPath = $base . DIRECTORY_SEPARATOR . 'public';
        if (is_dir($publicPath)) {
            $this->publishes([
                $publicPath => public_path('modules/' . $this->moduleName),
            ], $this->moduleName . '-assets');
        }
    }
}
