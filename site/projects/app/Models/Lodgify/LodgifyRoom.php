<?php

namespace App\Models\Lodgify;

use App\Models\Property;

/**
 * Compatibility shim.
 *
 * The front templates (home.blade.php and others) and PmsUpgradeViews reference
 * App\Models\Lodgify\LodgifyRoom, but that class was missing from the downloaded
 * server mirror. It represents the property/room listing, so this maps to the
 * existing Property model (table: properties) to keep the app bootable.
 */
class LodgifyRoom extends Property
{
    protected $table = 'properties';
}
