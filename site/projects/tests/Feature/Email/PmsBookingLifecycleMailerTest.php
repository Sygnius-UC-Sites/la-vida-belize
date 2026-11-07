<?php

namespace Tests\Feature\Email;

use App\Models\BookingRequest;
use App\Models\Payment;
use App\Models\Pms\PmsRoom;
use App\Services\Email\BookingLifecycleMailer;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PmsBookingLifecycleMailerTest extends TestCase
{
    public function test_pms_success_uses_admin_templates_and_frozen_quote_for_all_four_emails(): void
    {
        foreach ([
            'mail_from' => 'bookings@example.test',
            'mail_from_name' => 'Example Stays',
            'mailer_admin_name' => 'Example Stays',
            'payment_currency' => '$',
            'payment_receiving_mail' => 'owner@example.test',
            'confirmed_default_subject' => 'CUSTOM CONFIRMED {{booking_id}} {{property_name}}',
            'confirmed_default_body_html' => '<p>GUEST CONFIRMATION TEMPLATE {{guest_name}}</p>',
            'admin_alert_confirmed_subject' => 'CUSTOM OWNER CONFIRMED {{booking_id}}',
            'admin_alert_confirmed_body_html' => '<p>OWNER CONFIRMATION TEMPLATE</p>',
            'receipt_default_subject' => 'CUSTOM RECEIPT {{payment_amount}}',
            'receipt_default_body_html' => '<p>GUEST RECEIPT TEMPLATE {{transaction_id}}</p>',
            'admin_alert_receipt_subject' => 'CUSTOM OWNER RECEIPT {{payment_amount}}',
            'admin_alert_receipt_body_html' => '<p>OWNER RECEIPT TEMPLATE {{outstanding_balance}}</p>',
        ] as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $room = PmsRoom::create([
            'pms_property_id' => '483496',
            'pms_room_id' => '549777',
            'pms_source' => 'lodgify',
            'name' => 'Demo Garden Studio',
            'seo_url' => 'demo-garden-studio-test',
            'status' => 'active',
            'currency' => 'USD',
        ]);

        $booking = BookingRequest::create([
            'property_id' => $room->id,
            'pms_source' => 'lodgify',
            'pms_room_id' => '549777',
            'checkin' => '2027-02-10',
            'checkout' => '2027-02-13',
            'adults' => 2,
            'child' => 0,
            'total_guests' => 2,
            'total_pets' => 0,
            'total_night' => 3,
            'gross_amount' => 549,
            'sub_amount' => 649,
            'tax' => 32.94,
            'total_amount' => 681.94,
            'deposit_due_amount' => 340.97,
            'currency' => 'USD',
            'before_total_fees' => json_encode([
                ['name' => '3 nights', 'value' => 549, 'amount' => 549, 'type' => 'accommodation'],
                ['name' => 'Cleaning fee', 'value' => 100, 'amount' => 100, 'type' => 'cleaning'],
                ['name' => 'Taxes', 'value' => 32.94, 'amount' => 32.94, 'type' => 'tax'],
            ]),
            'after_total_fees' => json_encode([
                ['name' => 'Taxes', 'value' => 32.94, 'amount' => 32.94],
            ]),
            'amount_data' => json_encode([
                ['amount' => 340.97, 'date_due' => 'On agreement', 'label' => 'Deposit (due today)', 'is_current' => true],
                ['amount' => 340.97, 'date_due' => 'Feb 09 2027', 'label' => 'Balance', 'is_current' => false],
            ]),
            'quote_data_all' => json_encode([
                'data' => [[
                    'scheduled_damage_protection' => [[
                        'type' => 'Authorization',
                        'amount' => 68.19,
                    ]],
                ]],
            ]),
            'request_id' => 'pms-mailer-test',
            'booking_status' => 'booking-confirmed',
            'payment_status' => 'partially',
            'name' => 'Steven Test',
            'email' => 'guest@example.test',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'tran_id' => 'pi_test_exact_quote',
            'status' => 'complete',
            'type' => 'stripe',
            'amount' => 340.97,
        ]);

        $results = app(BookingLifecycleMailer::class)
            ->sendConfirmationAndReceipt($booking->fresh(), $room->fresh(), $payment->fresh());

        $this->assertSame([
            'confirmed' => true,
            'admin-confirmed' => true,
            'receipt' => true,
            'admin-receipt' => true,
        ], $results);

        $logs = DB::table('email_send_log')
            ->where('booking_id', $booking->id)
            ->get()
            ->keyBy('email_type');

        $this->assertCount(4, $logs);
        $this->assertSame('CUSTOM CONFIRMED ' . $booking->id . ' Demo Garden Studio', $logs['confirmed']->subject);
        $this->assertSame('owner@example.test', $logs['admin-confirmed']->recipient);
        $this->assertSame('CUSTOM RECEIPT $340.97', $logs['receipt']->subject);
        $this->assertSame('CUSTOM OWNER RECEIPT $340.97', $logs['admin-receipt']->subject);

        foreach ($logs as $log) {
            $this->assertSame(1, (int) $log->success);
            $this->assertStringContainsString('3 nights', $log->rendered_html);
            $this->assertStringContainsString('Cleaning fee', $log->rendered_html);
            $this->assertStringContainsString('Taxes', $log->rendered_html);
            $this->assertStringContainsString('$681.94', $log->rendered_html);
            $this->assertStringContainsString('Refundable security deposit hold', $log->rendered_html);
            $this->assertStringContainsString('$68.19', $log->rendered_html);
            $this->assertStringContainsString('Payment schedule', $log->rendered_html);
            $this->assertStringContainsString('Deposit (due today)', $log->rendered_html);
            $this->assertStringContainsString('Balance', $log->rendered_html);
            $this->assertStringNotContainsString('{{', $log->rendered_html);
        }

        $this->assertStringContainsString('GUEST CONFIRMATION TEMPLATE', $logs['confirmed']->rendered_html);
        $this->assertStringContainsString('OWNER CONFIRMATION TEMPLATE', $logs['admin-confirmed']->rendered_html);
        $this->assertStringContainsString('GUEST RECEIPT TEMPLATE pi_test_exact_quote', $logs['receipt']->rendered_html);
        $this->assertStringContainsString('OWNER RECEIPT TEMPLATE $340.97 remaining', $logs['admin-receipt']->rendered_html);

        // The PMS snapshot already contains accommodation and tax. They must
        // not be duplicated from gross_amount/tax/after_total_fees fallbacks.
        $this->assertSame(1, substr_count($logs['confirmed']->rendered_html, '$549.00'));
        $this->assertSame(1, substr_count($logs['confirmed']->rendered_html, '$32.94'));
    }

    public function test_post_confirmation_pms_agreement_sends_signing_request_once(): void
    {
        foreach ([
            'payment_receiving_mail' => 'owner@example.test',
            'rental_agreement_request_default_subject' => 'SIGN AGREEMENT {{booking_id}} {{property_name}}',
            'rental_agreement_request_default_body_html' => '<p>Agreement link: {{rental_agreement_link}}</p>',
        ] as $name => $value) {
            DB::table('basic_settings')->updateOrInsert(
                ['name' => $name],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }

        $room = PmsRoom::create([
            'pms_property_id' => 'agreement-mail-property',
            'pms_room_id' => 'agreement-mail-room',
            'pms_source' => 'hostfully',
            'name' => 'Agreement Mail PMS Room',
            'seo_url' => 'agreement-mail-pms-room',
            'status' => 'true',
            'currency' => 'USD',
            'rental_agreement_required' => 1,
            'rental_agreement_signing_mode' => 'post_confirmation',
        ]);

        $booking = BookingRequest::create([
            'property_id' => $room->id,
            'pms_source' => 'hostfully',
            'pms_room_id' => 'agreement-mail-room',
            'checkin' => '2027-04-10',
            'checkout' => '2027-04-13',
            'adults' => 2,
            'child' => 0,
            'total_guests' => 2,
            'total_pets' => 0,
            'total_night' => 3,
            'gross_amount' => 600,
            'sub_amount' => 600,
            'tax' => 0,
            'total_amount' => 600,
            'currency' => 'USD',
            'before_total_fees' => '[]',
            'after_total_fees' => '[]',
            'amount_data' => '[]',
            'request_id' => 'pms-agreement-mail-test',
            'booking_status' => 'booking-confirmed',
            'payment_status' => 'paid',
            'rental_aggrement_status' => 'false',
            'name' => 'Agreement Guest',
            'email' => 'agreement-guest@example.test',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'tran_id' => 'pi_test_agreement_mail',
            'status' => 'complete',
            'type' => 'stripe',
            'amount' => 600,
        ]);

        $first = app(BookingLifecycleMailer::class)
            ->sendConfirmationAndReceipt($booking->fresh(), $room->fresh(), $payment->fresh());
        $second = app(BookingLifecycleMailer::class)
            ->sendConfirmationAndReceipt($booking->fresh(), $room->fresh(), $payment->fresh());

        $this->assertArrayHasKey('rental-agreement-request', $first);
        $this->assertArrayNotHasKey('rental-agreement-request', $second);

        $agreementLogs = DB::table('email_send_log')
            ->where('booking_id', $booking->id)
            ->where('email_type', 'rental-agreement-request')
            ->get();

        $this->assertCount(1, $agreementLogs);
        $this->assertSame('agreement-guest@example.test', $agreementLogs[0]->recipient);
        $this->assertSame('SIGN AGREEMENT ' . $booking->id . ' Agreement Mail PMS Room', $agreementLogs[0]->subject);
        $this->assertStringContainsString('/booking/' . $booking->id . '/sign-agreement', $agreementLogs[0]->rendered_html);
    }
}
