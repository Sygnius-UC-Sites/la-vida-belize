<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Helper\MailHelper;

/**
 * Class HelperServiceProvider
 * @package App\Providers
 */
class MailHelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('MailHelper', function()
        {
            return new MailHelper;
        });
    }
}
