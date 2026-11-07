<?php

namespace Tests\Feature\Visitor;

use App\Models\Payment;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class PaymentIdempotencyTest extends TestCase
{
    use WithoutMiddleware;

    public function test_payment_first_or_create_dedupes_on_tran_id(): void
    {
        $tranId = 'test_chg_' . uniqid();

        $first = Payment::firstOrCreate(
            ['tran_id' => $tranId],
            [
                'booking_id' => 0,
                'amount' => 100,
                'status' => 'complete',
                'type' => 'stripe',
                'description' => 'idempotency test row',
            ]
        );

        $second = Payment::firstOrCreate(
            ['tran_id' => $tranId],
            [
                'booking_id' => 0,
                'amount' => 999,
                'status' => 'complete',
                'type' => 'stripe',
                'description' => 'should NOT overwrite',
            ]
        );

        $this->assertTrue($first->wasRecentlyCreated, 'first call should create the row');
        $this->assertFalse($second->wasRecentlyCreated, 'second call must NOT create a duplicate row');
        $this->assertSame($first->id, $second->id, 'both calls should return the same row');
        $this->assertSame(100.0, (float) $second->amount, 'second call must NOT overwrite the existing row');

        Payment::where('tran_id', $tranId)->delete();
    }
}
