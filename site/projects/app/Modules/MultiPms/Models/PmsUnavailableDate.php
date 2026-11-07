<?php
namespace App\Modules\MultiPms\Models;

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
		'property_id'
    ];
}
