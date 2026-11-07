<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmsProperty extends Model
{
    use HasFactory;
    protected $table = 'pms_properties';

    public $fillable=[

        "name",
        "pms_property_id",
        "latitude",
        "longitude",
        "address",
        "zip",
        "city",
        "country",
        "image_url",
        "has_addons",
        "has_agreement",
        "agreement_text",
        "agreement_url",
        "owner",
        "rating",
        "price_unit_in_days",
        "min_price",
        "original_min_price",
        "max_price",
        "original_max_price",
        "rooms",
        "in_out_max_date",
        "seo_url",
        "meta_title",
        "meta_keywords",
        "meta_description",
        "is_home",
        "status",
        "map",
        "banner_image",
        'currency',
        'subscription_plans','location_id','review_widget',
        'pms_source',
    
        // mutation-test fix (2026-06-11): columns missing from $fillable
        'virtual_tour',
    ];


    public function room(){
        return $this->hasOne(\App\Models\Pms\PmsRoom::class,"pms_property_id","pms_property_id");
    }
}
