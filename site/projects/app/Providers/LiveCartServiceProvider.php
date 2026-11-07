<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Helper\LiveCart;

/**
 * Class HelperServiceProvider
 * @package App\Providers
 */
class LiveCartServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('LiveCart', function()
        {
            return new LiveCart;
        });
    }
}
