<?php

namespace App\Modules\MultiPms\Pms;

use App\Modules\MultiPms\Pms\Adapters\LodgifyAdapter;
use App\Modules\MultiPms\Pms\Adapters\HostawayAdapter;
use App\Modules\MultiPms\Pms\Adapters\GuestyAdapter;
use App\Modules\MultiPms\Pms\Adapters\HostfullyAdapter;
use App\Modules\MultiPms\Pms\Adapters\CrmAdapter;
use InvalidArgumentException;

class PmsManager
{
    protected array $drivers = [];
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function driver(?string $driver = null): PmsAdapter
    {
        if (null === $driver) {
            try {
                // Read driver preference from CRM settings dynamically
                $driver = class_exists(\ModelHelper::class) 
                    ? \ModelHelper::getDataFromSetting('pms_driver') 
                    : null;
            } catch (\Throwable $e) {
                $driver = null;
            }
            $driver = $driver ?: config('pms.driver', 'lodgify');
        }

        $driver = strtolower($driver);

        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    protected function createDriver(string $driver): PmsAdapter
    {
        switch ($driver) {
            case 'lodgify':
                return new LodgifyAdapter();
            case 'hostaway':
                return new HostawayAdapter();
            case 'guesty':
                return new GuestyAdapter();
            case 'hostfully':
                return new HostfullyAdapter();
            case 'crm':
            case 'none':
                return new CrmAdapter();
            default:
                throw new InvalidArgumentException("PMS Driver [{$driver}] is not supported.");
        }
    }
}
