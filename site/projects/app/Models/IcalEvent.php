<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Testimonial
 * @package App\Models
 */
class IcalEvent extends Model
{
    public $table = "ical_events";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
		'id',
		'ppp_id',
		'ical_link',
		'start_date',
		'end_date',
		'text',
		'event_pid',
		'cat_id',
		'uid',
		'event_type',
		'booking_status'



    ]; 
}
