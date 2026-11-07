<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyRateGroup extends Model
{
    use HasFactory;
    protected $table="properties_rates_group";

     public $fillable=[
        "property_id",
        'name_of_price',
        'type_of_price',

        "start_date",
        "start_date_timestamp",
        "end_date",
        "end_date_timestamp",
        "price",
        "monday_price",
        "tuesday_price",
        'wednesday_price',
        "thrusday_price",
        "friday_price",
        "saturday_price",
        "sunday_price",

        "discount_weekly",
        "discount_monthly",
        "checkin_day",
        'checkout_day',


        "is_available",

        "platform_type",
        "currency",
        "base_price",
        "notes",
        "min_stay",
        "base_min_stay"
    ];
}
