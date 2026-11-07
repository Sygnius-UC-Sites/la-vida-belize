<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmsRate extends Model
{
    use HasFactory;

    protected $table = "pms_rates";

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
    
        // mutation-test fix (2026-06-11): columns missing from $fillable
        'pms_source',
    ];
}
