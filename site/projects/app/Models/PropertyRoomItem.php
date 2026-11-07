<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyRoomItem extends Model
{
    use HasFactory;
    public $table = "property_room_items";

    public $primaryKey = "id";

    public $timestamps = true;
    public $fillable=[
        "room_id",
        "sub_room_title",
        "sub_room_sub_title",
        "sub_room_description",
        "sub_room_status"
    ];
}
