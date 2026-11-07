<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Testimonial
 * @package App\Models
 */
class IcalImportList extends Model
{
    public $table = "ical_import_list";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
        'id',
        'ical_link',
        'property_id',
        'label',
        'color',
        'last_refreshed_at',
        'last_error',
    ];

    protected $casts = [
        'last_refreshed_at' => 'datetime',
    ];
}
