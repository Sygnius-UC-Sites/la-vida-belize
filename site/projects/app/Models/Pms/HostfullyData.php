<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HostfullyData extends Model
{
    use HasFactory;
    protected $table = 'pms_hostfully_data';
    protected $fillable = ['pms_property_id', 'raw_property_json', 'raw_units_json'];
}
