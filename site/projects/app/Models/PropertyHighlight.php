<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PropertyHighlight extends Model
{
    public $table = "property_highlights";
    public $primaryKey = "id";
    public $timestamps = true;
    use HasFactory;

    public $fillable = [
        "property_id",
        "icon",
        "title",
        "description",
        "sorting",
        "status",
    ];
}
