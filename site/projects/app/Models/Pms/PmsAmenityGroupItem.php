<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmsAmenityGroupItem extends Model
{
    use HasFactory;
    protected $table = 'pms_amenity_group_items';
    public $fillable=[
        "pms_property_id",
        "pms_room_id",
        "pms_amenity_group_id",
        "name",
        "prefix",
        "bracket",
        "text",
    
        // mutation-test fix (2026-06-11): columns missing from $fillable
        'pms_source',
    ];
}
