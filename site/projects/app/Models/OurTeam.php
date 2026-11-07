<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Testimonial
 * @package App\Models
 */
class OurTeam extends Model
{
    public $table = "our_teams";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
		'id',
		'name',
		'message',
		'image',
		'email',
		'mobile',
		'profile',
		'country',
		'state',
		'city',

    ]; 
}
