<?php

namespace Tests\Feature\Payment;

use App\Models\BookingRequest;
use App\Services\Payments\DirectInstallmentScheduler;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DirectInstallmentSchedulerTest extends TestCase
{
    public function test_creates_scheduled_payment_for_direct_two_installment_booking(): void
    {
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'second_how_many_days'],
            ['value' => '30', 'created_at' => now(), 'updated_at' => now()]
        );

        $checkin = Carbon::today()->addDays(90);
        $bookingId = DB::table('booking_requests')->insertGetId($this->bookingRow([
            'checkin' => $checkin->toDateString(),
            'checkout' => $checkin->copy()->addDays(3)->toDateString(),
            'total_payment' => 2,
            'amount_data' => json_encode([
                ['amount' => 600.00, 'type' => 'first', 'status' => 'complete'],
                ['amount' => 400.00, 'type' => 'second'],
            ]),
        ]));

        $created = app(DirectInstallmentScheduler::class)
            ->schedule(BookingRequest::findOrFail($bookingId), 'cus_direct_test', null, 'usd');

        $this->assertSame(1, $created);
        $row = DB::table('scheduled_payments')->where('booking_request_id', $bookingId)->first();
        $this->assertSame('crm', $row->pms_source);
        $this->assertSame('direct-' . $bookingId, $row->pms_booking_id);
        $this->assertSame('cus_direct_test', $row->stripe_customer_id);
        $this->assertSame(400.00, (float) $row->amount);
        $this->assertSame('USD', $row->currency);
        $this->assertSame($checkin->copy()->subDays(30)->toDateString(), Carbon::parse($row->due_date)->toDateString());
        $this->assertSame('pending', $row->status);

        DB::table('scheduled_payments')->where('booking_request_id', $bookingId)->delete();
        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }

    public function test_creates_second_and_third_scheduled_payments_for_direct_three_installment_booking(): void
    {
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'second_third_how_many_days'],
            ['value' => '60', 'created_at' => now(), 'updated_at' => now()]
        );
        DB::table('basic_settings')->updateOrInsert(
            ['name' => 'third_how_many_days'],
            ['value' => '15', 'created_at' => now(), 'updated_at' => now()]
        );

        $checkin = Carbon::today()->addDays(120);
        $bookingId = DB::table('booking_requests')->insertGetId($this->bookingRow([
            'checkin' => $checkin->toDateString(),
            'checkout' => $checkin->copy()->addDays(4)->toDateString(),
            'total_payment' => 3,
            'amount_data' => json_encode([
                ['amount' => 500.00, 'type' => 'first', 'status' => 'complete'],
                ['amount' => 250.00, 'type' => 'second'],
                ['amount' => 250.00, 'type' => 'third'],
            ]),
        ]));

        $created = app(DirectInstallmentScheduler::class)
            ->schedule(BookingRequest::findOrFail($bookingId), 'cus_direct_three', null, 'USD');

        $this->assertSame(2, $created);
        $rows = DB::table('scheduled_payments')
            ->where('booking_request_id', $bookingId)
            ->orderBy('due_date')
            ->get(['amount', 'due_date']);

        $this->assertCount(2, $rows);
        $this->assertSame($checkin->copy()->subDays(60)->toDateString(), Carbon::parse($rows[0]->due_date)->toDateString());
        $this->assertSame(250.00, (float) $rows[0]->amount);
        $this->assertSame($checkin->copy()->subDays(15)->toDateString(), Carbon::parse($rows[1]->due_date)->toDateString());
        $this->assertSame(250.00, (float) $rows[1]->amount);

        DB::table('scheduled_payments')->where('booking_request_id', $bookingId)->delete();
        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }

    public function test_uses_modern_quote_installment_due_dates_for_direct_bookings(): void
    {
        $checkin = Carbon::today()->addDays(120);
        $secondDue = Carbon::today()->addDays(21)->toDateString();
        $bookingId = DB::table('booking_requests')->insertGetId($this->bookingRow([
            'checkin' => $checkin->toDateString(),
            'checkout' => $checkin->copy()->addDays(4)->toDateString(),
            'total_payment' => 1,
            'amount_data' => json_encode([
                'total' => 1000.00,
                'installments' => [
                    ['due_date' => Carbon::today()->toDateString(), 'amount' => 600.00, 'sequence' => 1],
                    ['due_date' => $secondDue, 'amount' => 400.00, 'sequence' => 2],
                ],
            ]),
        ]));

        $created = app(DirectInstallmentScheduler::class)
            ->schedule(BookingRequest::findOrFail($bookingId), 'cus_direct_modern', null, 'USD');

        $this->assertSame(1, $created);
        $row = DB::table('scheduled_payments')->where('booking_request_id', $bookingId)->first();
        $this->assertSame($secondDue, Carbon::parse($row->due_date)->toDateString());
        $this->assertSame(400.00, (float) $row->amount);

        DB::table('scheduled_payments')->where('booking_request_id', $bookingId)->delete();
        DB::table('booking_requests')->where('id', $bookingId)->delete();
    }

    private function bookingRow(array $overrides = []): array
    {
        return array_merge([
            'property_id' => 1,
            'name' => 'Direct Installment Test',
            'email' => 'direct-installment@example.com',
            'booking_status' => 'booked',
            'payment_status' => 'partially',
            'how_many_payment_done' => 1,
            'before_total_fees' => '[]',
            'after_total_fees' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }
}
