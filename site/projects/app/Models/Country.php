<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    public $table = "countries";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
		'id',
		'name',
		'iso3',
		'numeric_code',
		'iso2',
		'phonecode',
		'capital',
		'currency',
		'currency_name',
		'currency_symbol',
		'tld',
		'native',
		'region',
		'subregion',

		'timezones',
		'translations',
		'latitude',
		'longitude',
		'emoji',
		'emojiU',
		'flag',
		'wikiDataId',

			'meta_title',
		'meta_keywords',
		'meta_description',
'seo_url',
		'header_section',
		'footer_section',
		'bannerImage',
		'seo_text'

    ];



    public function properties(){
        return $this->hasMany(\App\Models\Property::class,"city","id");
    }
}

