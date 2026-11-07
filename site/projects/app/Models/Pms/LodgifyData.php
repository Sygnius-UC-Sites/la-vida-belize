<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LodgifyData extends Model
{
    use HasFactory;
    protected $table = 'pms_lodgify_data';
    protected $fillable = ['pms_property_id', 'raw_property_json', 'raw_units_json'];
}
