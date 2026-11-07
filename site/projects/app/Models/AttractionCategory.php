<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttractionCategory extends Model
{
    public $table = "attraction_categories";

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
		'attraction_image',
		'meta_title',
		'meta_keywords',
		'meta_description',
		'templete',
		'bannerImage',
		'publish',

		'header_section',
		'footer_section',
		'ordering',
    ];

    public static function mediaUrl(?string $path): string
    {
        return Attraction::mediaUrl($path);
    }

    public function imageUrl(): string
    {
        return self::mediaUrl($this->image ?: $this->bannerImage);
    }

    public function bannerUrl(): string
    {
        return self::mediaUrl($this->bannerImage ?: $this->image);
    }

    public function guestUrl(): string
    {
        return url('/attractions/' . ltrim((string) $this->seo_url, '/'));
    }

    public static $rules = [
        // create rules
    ];

    // Cm 
}

