<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyAmenityHighlight extends Model
{
    public $table = "property_amenity_highlights";
    public $primaryKey = "id";
    public $timestamps = true;
    use HasFactory;

    public $fillable = [
        "property_id",
        "emoji",
        "label",
        "sorting",
        "status",
    ];
}
