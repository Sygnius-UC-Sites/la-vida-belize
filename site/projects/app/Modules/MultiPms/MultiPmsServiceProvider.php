<?php

namespace App\Modules\MultiPms;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\MultiPms\Pms\PmsManager;
use Illuminate\Support\Facades\Route;

class MultiPmsServiceProvider extends AbstractModuleServiceProvider
{
    protected string $moduleName      = 'multi-pms';
    protected string $moduleNamespace = 'App\\Modules\\MultiPms';

    public function register(): void
    {
        // Bind PmsManager as a singleton so adapters share the same instance
        $this->app->singleton(PmsManager::class, function ($app) {
            return new PmsManager($app);
        });

        // Bind PmsBookingService
        $this->app->bind(PmsBookingService::class, function ($app) {
            return new PmsBookingService($app->make(PmsManager::class));
        });
    }

    public function boot(): void
    {
        parent::boot(); // loads migrations, admin routes, views, and public routes
        // public routes/public.php is already registered by parent::boot() — do not re-register
    }
}
