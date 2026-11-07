<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Testimonial
 * @package App\Models
 */
class Testimonial extends Model
{
    public $table = "testimonials";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
        // [PMS-MODULE] fillable additions (duplicates are harmless)
        'pms_source', 'pms_review_id', 'end_date',

		'id',
		'name',
		'message',
		'image',
		'email',
		'mobile',
		'profile',
		'score',
		'stay_date',
		'end_date',
		'property_id',
		'status',
      'is_home',
        'booking_id',
        'submission_token',
        'rating',
        'submitted_at',
        'submitted_ip',
    ];
}
