<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmsAmenityGroup extends Model
{
    use HasFactory;
    protected $table = 'pms_amenity_groups';
    public $fillable=[
        "pms_property_id",
        "pms_room_id",
        "text",
    
        // mutation-test fix (2026-06-11): columns missing from $fillable
        'pms_source',
    ];
}
