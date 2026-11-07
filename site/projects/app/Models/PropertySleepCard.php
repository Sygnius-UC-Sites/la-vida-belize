<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertySleepCard extends Model
{
    public $table = "property_sleep_cards";
    public $primaryKey = "id";
    public $timestamps = true;
    use HasFactory;

    public $fillable = [
        "property_id",
        "title",
        "text",
        "sorting",
        "status",
    ];
}
