<?php
namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmsUnavailableDate extends Model
{
    public $table = "pms_unavailable_dates";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
		'id',
		'un_date',
		'property_id',
    
        // mutation-test fix (2026-06-11): columns missing from $fillable
        'pms_source',
    ];
}
