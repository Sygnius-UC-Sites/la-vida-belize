<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FaqCategory extends Model
{
    public $table = "faq_categories";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
        'id',
        'name',
        'seo_url',
        'ordering',
        'publish',
    ];

    public static $rules = [];

    public function faqs()
    {
        return $this->hasMany(\App\Models\Faq::class, 'type', 'seo_url');
    }
}
