<?php

namespace App\Console\Commands;

use App\Models\BookingRequest;
use Illuminate\Console\Command;

class MarkBookingsCompleted extends Command
{
    protected $signature = 'bookings:mark-completed';
    protected $description = 'Mark bookings as completed once checkout date has passed and payment is fully paid.';

    public function handle(): int
    {
        $today = date('Y-m-d');

        $count = BookingRequest::where('booking_status', 'booked')
            ->where('payment_status', 'paid')
            ->where('checkout', '<', $today)
            ->update(['booking_status' => 'completed', 'updated_at' => now()]);

        $this->info("Marked {$count} bookings as completed");
        return self::SUCCESS;
    }
}
