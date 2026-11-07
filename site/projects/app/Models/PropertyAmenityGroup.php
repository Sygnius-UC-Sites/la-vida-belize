<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyAmenityGroup extends Model
{
    use HasFactory;

    public $fillable=[
        "property_id",
        "status",
        "name",
        "image",
        "icon",
        "sorting"
    ];
}
