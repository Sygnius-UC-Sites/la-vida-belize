<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Helper
 * @package App\Facades
 */
class Helper extends Facade
{
    /**
     * Create the Facade
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'Helper'; }
}