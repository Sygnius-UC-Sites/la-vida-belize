<?php

namespace App\Modules\MultiPms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmsImage extends Model
{
    use HasFactory;
    protected $table = 'pms_images';

    public $fillable=[

        "pms_property_id",
        "pms_room_id",
        "text",
        "url",
    ];
}
