<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use App\Helper\ModelHelper;

class ModelHelperServiceProvider extends ServiceProvider{

    public function register(){
        $this->app->bind('ModelHelper', function(){
            return new ModelHelper;
        });
    }
}