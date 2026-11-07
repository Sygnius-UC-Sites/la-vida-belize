<?php

namespace App\Modules\MultiPms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmsRoom extends Model
{
    use HasFactory;
    protected $table = 'pms_rooms';

    public $fillable=[
        "pms_property_id",
        "pms_room_id",
        "description",
        "breakfast_included",
        "has_parking",
        "adults_only",
        "pets_allowed",
        "show_additional_key_facts",
        "name",
        "image_url",
        "max_people",
        "units",
        "has_wifi",
        "has_meal_plan",
        "bedrooms",
        "bathrooms",
        "area_unit",
        "area",
        "min_price",
        "original_min_price",
        "max_price",
        "original_max_price",
        "price_unit_in_days",
        "currency",
        "images_json",
        "amenities_json",
        "seo_url",
        "meta_title",
        "meta_keywords",
        "meta_description",
        'is_home',
        'status',
        'banner_image',
        'location_id',
        'review_widget',
        'map','ordering','custom_min_stay',
        'api_id',
        'api_pms','virtual_tour',
        'house_rules',
        'cancellation_policy',
        'booking_policy',
        'key_facts_json',
        'pricing_json',
        'pms_source',
        'address',
        'city',
        'state',
        'country',
        'zip',
        'lat',
        'lng',
        'property_type',
        'bed_config_json',
    ];
}
