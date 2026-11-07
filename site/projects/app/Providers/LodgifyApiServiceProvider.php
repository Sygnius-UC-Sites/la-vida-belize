<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use App\Helper\LodgifyApi;
/**
 * Class LodgifyApiServiceProvider
 * @package App\Providers
 */
class LodgifyApiServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('LodgifyApi', function()
        {
            return new LodgifyApi;
        });
    }
}