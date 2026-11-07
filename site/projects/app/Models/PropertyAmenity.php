<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyAmenity extends Model
{
    use HasFactory;

    public $fillable=[
         "property_amenity_id",
            "name",
            "status",
            "image",
            "icon",
            "is_card_highlight",
            "sorting"
    ];
}
