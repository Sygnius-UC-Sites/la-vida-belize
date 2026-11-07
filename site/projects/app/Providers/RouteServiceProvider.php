<?php
namespace App\Providers;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/admin';
    protected $namespace = 'App\\Http\\Controllers';
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot(){
        $this->configureRateLimiting();
        $this->routes(function () {
            Route::middleware('api')->prefix('api')->namespace($this->namespace)->group(base_path('routes/api.php'));
            Route::middleware('web')->namespace($this->namespace)->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting(){
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        foreach ((array) config('spam.forms', []) as $form) {
            $key = $form['rate_limit_key'] ?? null;
            $max = (int) ($form['rate_limit_max'] ?? 5);
            if (!$key) continue;
            RateLimiter::for($key, function (Request $request) use ($max) {
                return Limit::perMinute($max)->by($request->ip());
            });
        }
    }
}
