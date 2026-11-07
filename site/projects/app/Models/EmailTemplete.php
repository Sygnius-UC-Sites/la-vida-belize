<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailTemplete extends Model
{
    public $table = "email_templetes";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
		'id',
		'email_type',
		'email_subject',
		'email_body'

    ];

    public static $rules = [
        // create rules
    ];

    // Brand 
}
