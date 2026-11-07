<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyRate extends Model
{
    use HasFactory;

     public $fillable=[
        "property_id",
        "single_date",
        "single_date_timestamp",
        "price",
        "is_available",

        "platform_type",
        "currency",
        "base_price",
        "notes",
        "min_stay",
        "base_min_stay",
        "checkin_day",
        'checkout_day',
        "discount_weekly",
        "discount_monthly",
        'rate_group_id',
       "type_of_price",
        "fileid",
        "filename",
    ];
}
