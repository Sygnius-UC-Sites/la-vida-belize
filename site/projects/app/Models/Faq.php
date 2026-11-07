<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    public $table = "faqs";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
		'id',
		"question",
        "answer",
        'type',
        "question_ger",
        "answer_ger",
		'property_id',
    ];

    public static $rules = [
       
    ];

}
