<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    public $table = "states";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
		'id',
		'name',
		'country_id',
		'country_code',
		'fips_code',
		'iso2',
		'type',
		
		'latitude',
		'longitude',
	
		'flag',
		'wikiDataId',

		'meta_title',
		'meta_keywords',
		'meta_description',
		'seo_url',
		'header_section',
		'footer_section',
		'bannerImage',
		'is_featured',
		'image',
		'seo_text'

    ];



    public function properties(){
        return $this->hasMany(\App\Models\Property::class,"state","id");
    }
}

