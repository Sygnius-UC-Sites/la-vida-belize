<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactusRequest extends Model
{
    public $table = "contactus_requests";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
		'id',
		'name',
		'email',
		'mobile',
		'message',
		'property',
		'property_slug',
		'intent',
		'check_in',
		'check_out',
		'guests',
    
        // mutation-test fix (2026-06-11): columns missing from $fillable
        'date_of_request',
        'budget',
    ];

    public static $rules = [
        // create rules
    ];

    // Cm 
}

