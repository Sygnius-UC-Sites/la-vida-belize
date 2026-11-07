<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class ModelHelper
 * @package App\Facades
 */
class ModelHelper extends Facade
{
    /**
     * Create the Facade
     *
     * @return string
     */
    protected static function getFacadeAccessor() { return 'ModelHelper'; }
}