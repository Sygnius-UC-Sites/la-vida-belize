<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyGallery extends Model
{
    use HasFactory;

    public $fillable=[
        "property_id",
            "status",
            "image",
            "category",
            "sorting",
            "caption"
    ];
}
