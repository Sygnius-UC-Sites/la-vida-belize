<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyRoomItemImage extends Model
{
    use HasFactory;
    public $table = "property_room_item_images";

    public $primaryKey = "id";

    public $timestamps = true;
    public $fillable=[
        "sub_room_id",
        "sub_room_image"
    ];
}
