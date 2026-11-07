<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class Helper
 * @package App\Facades
 */
class LiveCart extends Facade
{
    /**
     * Create the Facade
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'LiveCart'; }
}