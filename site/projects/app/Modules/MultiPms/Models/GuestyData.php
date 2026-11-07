<?php

namespace App\Modules\MultiPms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestyData extends Model
{
    use HasFactory;
    protected $table = 'pms_guesty_data';
    protected $fillable = ['pms_property_id', 'raw_property_json', 'raw_units_json'];
}
