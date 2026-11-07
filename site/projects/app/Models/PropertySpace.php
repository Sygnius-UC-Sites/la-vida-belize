<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertySpace extends Model
{
    use HasFactory;
    public $table = "property_spaces";

    public $primaryKey = "id";

    public $timestamps = true;
    public $fillable=[
        "property_id",
        "space_name",
        "space_image",
        "space_status"
    ];
}
