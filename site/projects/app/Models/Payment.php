<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    public $table = "payments";

    public $primaryKey = "id";

    public $timestamps = true;

    public $fillable = [
        // [PMS-MODULE] fillable additions (duplicates are harmless)
        'pms_payment_id', 'pms_sync_status', 'pms_sync_error',

		'id',
		'booking_id',
        'receipt_url',
        'customer_id',
        'balance_transaction',
        'tran_id',
        'description',
        'status',
        'type',
        'amount'
    ];
}
