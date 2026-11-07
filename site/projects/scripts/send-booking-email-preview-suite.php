#!/usr/bin/env php
<?php

use App\Http\Controllers\Admin\BookingRequestController;
use App\Models\BookingRequest;
use App\Models\Property;
use App\Services\Stay\StayCommunicationsResolver;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$options = getopt('', ['to:', 'send', 'dry-run']);
$to = trim((string) ($options['to'] ?? ''));
$send = array_key_exists('send', $options);

if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/send-booking-email-preview-suite.php --to=email@example.com [--send|--dry-run]\n");
    exit(2);
}

if (!$send && !array_key_exists('dry-run', $options)) {
    fwrite(STDERR, "Refusing to send without --send. Use --dry-run to list the suite.\n");
    exit(2);
}

function preview_property(): Property
{
    return Property::first() ?: new Property([
        'id' => 0,
        'name' => 'Sample Base Property',
        'address' => 'Sample Address',
        'type' => 'home',
        'standard_rate' => 250,
    ]);
}

function preview_booking(Property $property): BookingRequest
{
    $booking = new BookingRequest([
        'name' => 'Sample Guest',
        'email' => 'guest@example.com',
        'mobile' => '+1 (555) 123-4567',
        'property_id' => $property->id ?? 0,
        'checkin' => now()->addDays(30)->toDateString(),
        'checkout' => now()->addDays(35)->toDateString(),
        'adults' => 2,
        'child' => 0,
        'total_pets' => 1,
        'gross_amount' => 1250,
        'tax' => 100,
        'currency' => 'USD',
        'before_total_fees' => json_encode([
            ['name' => '5 nights', 'amount' => 1250, 'value' => 1250, 'type' => 'accommodation'],
            ['name' => 'Cleaning fee', 'amount' => 100, 'value' => 100, 'type' => 'cleaning'],
            ['name' => 'Pet fee', 'amount' => 50, 'value' => 50, 'type' => 'pet'],
            ['name' => 'Taxes', 'amount' => 100, 'value' => 100, 'type' => 'tax'],
        ]),
        'amount_data' => json_encode([
            ['label' => 'Deposit due today', 'amount' => 750, 'date_due' => 'Today', 'is_current' => true],
            ['label' => 'Balance', 'amount' => 750, 'date_due' => now()->addDays(29)->toDateString(), 'is_current' => false],
        ]),
        'total_amount' => 1500,
        'total_night' => 5,
        'total_payment' => 3,
        'how_many_payment_done' => 1,
        'second_payment_amount' => 450,
        'third_payment_amount' => 300,
        'booking_status' => 'booked',
        'payment_status' => 'partially',
    ]);
    $booking->id = 999999;
    return $booking;
}

function brand_name(): string
{
    return \ModelHelper::getDataFromSetting('mailer_admin_name')
        ?: \ModelHelper::getDataFromSetting('mail_from_name')
        ?: \ModelHelper::getDataFromSetting('owner_name')
        ?: 'Base Vacation Rentals';
}

function shell_html(string $body): string
{
    return view('mail.email-shell', [
        'body_html_rendered' => $body,
        'brand' => brand_name(),
        'mail_footer' => \ModelHelper::getDataFromSetting('mail_footer') ?: '',
    ])->render();
}

function extras(): array
{
    return [
        '{{magic_link}}' => url('/#sample-magic-link'),
        '{{review_url}}' => url('/#sample-review-link'),
        '{{rental_agreement_link}}' => url('/#sample-rental-agreement-link'),
        '{{installment_number}}' => '2',
        '{{installment_amount}}' => '$450.00',
        '{{installment_due_date}}' => now()->addDays(15)->format('M j, Y'),
        '{{payment_amount}}' => '$750.00',
        '{{paid_amount}}' => '$750.00',
        '{{balance_due}}' => '$750.00',
        '{{payment_date}}' => now()->format('M j, Y - g:i A'),
        '{{payment_method}}' => 'CARD',
        '{{transaction_id}}' => 'pi_3SAMPLE0XYZ123abcdef',
        '{{receipt_url}}' => url('/#sample-receipt'),
        '{{outstanding_balance}}' => '$750.00 remaining',
        '{{cancel_date}}' => now()->format('M j, Y'),
    ];
}

function subject_from_preview_html(string $html, string $fallback): string
{
    if (preg_match('/<strong>Subject:<\/strong>\s*(.*?)<\/div>/is', $html, $m)) {
        $subject = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $subject !== '' ? $subject : $fallback;
    }
    return $fallback;
}

function test_banner(string $audience, string $label, string $type, string $originalSubject): string
{
    return '<div style="font-family:Arial,sans-serif;background:#eef6ff;color:#0f3557;border-bottom:1px solid #b7d7f2;padding:14px 24px;font-size:13px;line-height:1.5;">'
        . '<strong>BASE EMAIL TEST</strong><br>'
        . '<strong>Audience:</strong> ' . e($audience) . '<br>'
        . '<strong>Template:</strong> ' . e($label) . ' (' . e($type) . ')<br>'
        . '<strong>Original subject:</strong> ' . e($originalSubject) . '<br>'
        . 'Sample booking data only. No booking, PMS reservation, payment, rental agreement, or customer record was created.'
        . '</div>';
}

function render_from_template_keys(
    string $audience,
    string $type,
    string $label,
    string $subjectKey,
    string $bodyKey,
    string $subjectFallback,
    string $bodyFallback,
    bool $includeQuote = false,
): array {
    $property = preview_property();
    $booking = preview_booking($property);
    $resolver = new StayCommunicationsResolver($property, $booking);
    $subjectTemplate = (string) (\ModelHelper::getDataFromSetting($subjectKey) ?: $subjectFallback);
    $bodyTemplate = (string) (\ModelHelper::getDataFromSetting($bodyKey) ?: $bodyFallback);
    $subject = $resolver->expandMergeFields($subjectTemplate, extras());
    $body = $includeQuote
        ? $resolver->expandMergeFieldsWithQuote($bodyTemplate, extras())
        : $resolver->expandMergeFields($bodyTemplate, extras());

    return [
        'audience' => $audience,
        'type' => $type,
        'label' => $label,
        'original_subject' => $subject,
        'html' => test_banner($audience, $label, $type, $subject) . shell_html($body),
    ];
}

function render_gallery_preview(string $audience, string $type, string $label): array
{
    $response = app(BookingRequestController::class)->templatePreviewRender($type);
    $html = (string) $response->getContent();
    $originalSubject = subject_from_preview_html($html, $label);

    return [
        'audience' => $audience,
        'type' => $type,
        'label' => $label,
        'original_subject' => $originalSubject,
        'html' => test_banner($audience, $label, $type, $originalSubject) . $html,
    ];
}

function render_payment_reminder(): array
{
    $property = preview_property();
    $booking = preview_booking($property);
    $subject = 'Upcoming Payment Reminder - Booking #' . $booking->id;
    $html = view('mail.payment-reminder-user', [
        'guestName' => $booking->name,
        'propertyName' => $property->name ?: 'your property',
        'amount' => 450,
        'currency' => \ModelHelper::getDataFromSetting('payment_currency') ?: '$',
        'dueDate' => now()->addDays(7)->toDateString(),
        'bookingId' => $booking->id,
        'checkin' => $booking->checkin,
    ])->render();

    return [
        'audience' => 'GUEST',
        'type' => 'scheduled-payment-reminder',
        'label' => 'Scheduled payment reminder',
        'original_subject' => $subject,
        'html' => test_banner('GUEST', 'Scheduled payment reminder', 'scheduled-payment-reminder', $subject) . $html,
    ];
}

function render_payment_failure_alert(): array
{
    $booking = preview_booking(preview_property());
    $subject = 'Action Required: Scheduled Payment Failed - Booking #' . $booking->id;
    $html = view('mail.payment-failed-alert', [
        'bookingId' => $booking->id,
        'guestName' => $booking->name,
        'guestEmail' => $booking->email,
        'amount' => 450,
        'currency' => 'USD',
        'dueDate' => now()->toDateString(),
        'pmsSource' => 'lodgify',
        'pmsBookingId' => 'SAMPLE-PMS-BOOKING',
        'errorMessage' => 'Sample failure reason for email preview only.',
        'attempts' => 3,
    ])->render();

    return [
        'audience' => 'OWNER',
        'type' => 'scheduled-payment-failed',
        'label' => 'Scheduled payment failed alert',
        'original_subject' => $subject,
        'html' => test_banner('OWNER', 'Scheduled payment failed alert', 'scheduled-payment-failed', $subject) . $html,
    ];
}

$suite = [
    render_gallery_preview('GUEST', 'contact-guest', 'Contact form guest auto-reply'),
    render_gallery_preview('OWNER', 'contact-owner', 'Contact form owner alert'),
    render_gallery_preview('GUEST', 'enquiry', 'Enquiry confirmation'),
    render_from_template_keys('OWNER', 'admin-alert-enquiry', 'Owner alert - new booking enquiry', 'admin_alert_enquiry_subject', 'admin_alert_enquiry_body_html', 'New booking enquiry - {{property_name}}', '<p>New booking enquiry: #{{booking_id}}</p><p><a href="{{admin_url}}">View booking</a></p>', true),
    render_from_template_keys('GUEST', 'declined', 'Decline notice', 'decline_default_subject', 'decline_default_body_html', 'Update on your booking request - {{property_name}}', '<p>Hi {{guest_name}},</p><p>Thank you for your interest in <strong>{{property_name}}</strong>. Unfortunately we are unable to accept your request for these dates.</p><p>Warm regards,<br>{{brand}}</p>', false),
    render_gallery_preview('GUEST', 'awaiting-payment', 'Awaiting payment'),
    render_from_template_keys('OWNER', 'admin-alert-awaiting-payment', 'Owner alert - payment link sent', 'admin_alert_awaiting_payment_subject', 'admin_alert_awaiting_payment_body_html', 'Payment link sent - booking #{{booking_id}} ({{property_name}})', '<p>Magic link sent for booking #{{booking_id}}.</p><p><a href="{{admin_url}}">View booking</a></p>', true),
    render_gallery_preview('GUEST', 'confirmed', 'Booking confirmed'),
    render_from_template_keys('OWNER', 'admin-alert-confirmed', 'Owner alert - booking confirmed', 'admin_alert_confirmed_subject', 'admin_alert_confirmed_body_html', 'Booking confirmed - {{property_name}}', '<p>Booking #{{booking_id}} confirmed.</p><p><a href="{{admin_url}}">View booking</a></p>', true),
    render_gallery_preview('GUEST', 'receipt', 'Payment receipt'),
    render_from_template_keys('OWNER', 'admin-alert-receipt', 'Owner alert - payment receipt', 'admin_alert_receipt_subject', 'admin_alert_receipt_body_html', '[Payment received] #{{booking_id}}', '<p>{{payment_amount}} received on booking #{{booking_id}}.</p>', true),
    render_gallery_preview('GUEST', 'welcome', 'Welcome package'),
    render_from_template_keys('OWNER', 'admin-alert-welcome', 'Owner alert - welcome email sent', 'admin_alert_welcome_subject', 'admin_alert_welcome_body_html', 'Welcome email sent - booking #{{booking_id}}', '<p>Welcome email sent for booking #{{booking_id}}.</p>', false),
    render_gallery_preview('GUEST', 'checkin', 'Check-in info'),
    render_from_template_keys('OWNER', 'admin-alert-checkin', 'Owner alert - check-in email sent', 'admin_alert_checkin_subject', 'admin_alert_checkin_body_html', 'Check-in email sent - booking #{{booking_id}}', '<p>Check-in email sent for booking #{{booking_id}}.</p>', false),
    render_gallery_preview('GUEST', 'checkout', 'Check-out info'),
    render_from_template_keys('OWNER', 'admin-alert-checkout', 'Owner alert - check-out email sent', 'admin_alert_checkout_subject', 'admin_alert_checkout_body_html', 'Check-out email sent - booking #{{booking_id}}', '<p>Check-out email sent for booking #{{booking_id}}.</p>', false),
    render_gallery_preview('GUEST', 'review', 'Review request'),
    render_gallery_preview('GUEST', 'cancellation', 'Cancellation notice'),
    render_from_template_keys('OWNER', 'admin-alert-cancelled', 'Owner alert - booking cancelled', 'admin_alert_cancelled_subject', 'admin_alert_cancelled_body_html', 'Booking #{{booking_id}} cancelled - {{property_name}}', '<p>Booking #{{booking_id}} cancelled.</p><p><a href="{{admin_url}}">View booking</a></p>', true),
    render_gallery_preview('GUEST', 'installment2', 'Installment 2 reminder'),
    render_from_template_keys('OWNER', 'admin-alert-installment2', 'Owner alert - installment 2 reminder sent', 'admin_alert_installment2_subject', 'admin_alert_installment2_body_html', 'Installment 2 reminder sent - booking #{{booking_id}} ({{property_name}})', '<p>Installment 2 reminder sent for booking #{{booking_id}}.</p>', false),
    render_gallery_preview('GUEST', 'installment3', 'Final installment reminder'),
    render_from_template_keys('OWNER', 'admin-alert-installment3', 'Owner alert - final installment reminder sent', 'admin_alert_installment3_subject', 'admin_alert_installment3_body_html', 'Final installment reminder sent - booking #{{booking_id}} ({{property_name}})', '<p>Final installment reminder sent for booking #{{booking_id}}.</p>', false),
    render_payment_reminder(),
    render_payment_failure_alert(),
    render_gallery_preview('GUEST', 'rental-agreement-request', 'Rental agreement please-sign link'),
    render_gallery_preview('GUEST', 'rental-agreement-signed', 'Rental agreement signed guest copy'),
    render_gallery_preview('OWNER', 'rental-agreement-signed-admin', 'Rental agreement signed owner alert'),
];

$total = count($suite);
$results = [];

foreach ($suite as $index => $email) {
    $number = $index + 1;
    $subject = sprintf(
        '[BASE EMAIL TEST][%s] %02d/%02d %s',
        $email['audience'],
        $number,
        $total,
        $email['label']
    );

    $ok = true;
    $error = null;
    if ($send) {
        try {
            $ok = \MailHelper::emailSenderByController($email['html'], $to, $subject);
            if (!$ok) {
                $error = 'MailHelper returned false.';
            }
        } catch (Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
        }
        usleep(150000);
    }

    $results[] = [
        'number' => $number,
        'total' => $total,
        'sent' => $send,
        'ok' => $ok,
        'audience' => $email['audience'],
        'type' => $email['type'],
        'label' => $email['label'],
        'to' => $to,
        'subject' => $subject,
        'original_subject' => $email['original_subject'],
        'error' => $error,
    ];
}

$passed = count(array_filter($results, static fn (array $row): bool => (bool) $row['ok']));

$summary = [
    'mode' => $send ? 'send' : 'dry-run',
    'to' => $to,
    'total' => $total,
    'ok' => $passed,
    'failed' => $total - $passed,
    'guest_count' => count(array_filter($suite, static fn (array $row): bool => $row['audience'] === 'GUEST')),
    'owner_count' => count(array_filter($suite, static fn (array $row): bool => $row['audience'] === 'OWNER')),
    'results' => $results,
    'note' => 'Sample booking data only. No booking, PMS reservation, payment, rental agreement, or customer record was created. Mail send logs may be written by the mailer.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

exit($summary['failed'] === 0 ? 0 : 1);
