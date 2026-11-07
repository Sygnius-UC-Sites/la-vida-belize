<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyRoom extends Model
{
    use HasFactory;
    public $table = "property_rooms";

    public $primaryKey = "id";

    public $timestamps = true;
    public $fillable=[
        "property_id",
        "room_title",
        "room_sub_title",
        "room_description",
        "room_status"
    ];
}
