<?php

namespace App\Modules\MultiPms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PmsBooking extends Model
{
    use HasFactory;
    protected $table = 'pms_bookings';
    public $fillable=[
        "arrival",
        "departure",
        "people",
        "property_id",
        "pms_booking_id",
        "latest_webhook_event_id",
        "type",
        "booking_type",
        "status",
        "source",
        "source_text",
        "guest",
        "property_name",
        "rooms",
        'room_ids',
        "is_replied",
        "is_deleted",
        "date_deleted",
        "total_amount",
        "total_paid",
        "amount_to_pay",
        "currency",
        "pms_source",
    ];
}
