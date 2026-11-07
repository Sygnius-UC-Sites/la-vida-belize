<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Attraction extends Model
{
    public $table = "attractions";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
		'id',
		'name',
		'seo_url',
		'shortDescription',
		'mediumDescription',
		'longDescription',
		'description',
		'image',
		'meta_title',
		'meta_keywords',
		'meta_description',
		'templete',
		'bannerImage',
		'publish',
		'header_section',
		'footer_section',
		'location_id',
		'property_id',
		'ordering',
		'category_id',
		'type',
		'mobile',
		'address',
		'map_url',
		'category_type',
    ];

    public static $rules = [];

    public function property()
    {
        return $this->belongsTo(\App\Models\Property::class, 'property_id');
    }

    public function location()
    {
        return $this->belongsTo(\App\Models\Location::class, 'location_id');
    }

    public function category()
    {
        return $this->belongsTo(\App\Models\AttractionCategory::class, 'category_id');
    }
}