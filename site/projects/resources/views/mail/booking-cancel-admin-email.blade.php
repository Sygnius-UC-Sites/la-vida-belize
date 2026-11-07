@php
    $guestName = data_get($data ?? [], 'name') ?: 'Guest';
    $guestEmail = data_get($data ?? [], 'email') ?: 'N/A';
    $propertyName = data_get($property ?? null, 'name') ?: 'Property';
    $checkin = data_get($data ?? [], 'checkin') ?: 'N/A';
    $checkout = data_get($data ?? [], 'checkout') ?: 'N/A';
    $bookingId = data_get($data ?? [], 'request_id') ?: data_get($data ?? [], 'id');
    $pmsBookingId = data_get($data ?? [], 'pms_booking_id');
    $currency = data_get($data ?? [], 'currency') ?: 'USD';
    $total = data_get($data ?? [], 'total_amount');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Booking Cancellation</title>
</head>
<body style="margin:0;padding:0;background:#f7f7f7;font-family:Arial,sans-serif;color:#1f2933;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f7f7;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="max-width:640px;width:100%;background:#ffffff;border:1px solid #e5e7eb;border-radius:12px;">
                <tr>
                    <td style="padding:28px;">
                        <h1 style="margin:0 0 16px;font-size:24px;line-height:1.25;color:#111827;">Booking cancelled</h1>
                        <p style="margin:0 0 20px;font-size:15px;line-height:1.6;">
                            A PMS booking for <strong>{{ $propertyName }}</strong> has been cancelled.
                        </p>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 20px;">
                            @if($bookingId)
                                <tr>
                                    <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;">Booking reference</td>
                                    <td style="padding:10px;border:1px solid #e5e7eb;">{{ $bookingId }}</td>
                                </tr>
                            @endif
                            @if($pmsBookingId)
                                <tr>
                                    <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;">PMS booking ID</td>
                                    <td style="padding:10px;border:1px solid #e5e7eb;">{{ $pmsBookingId }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;">Guest</td>
                                <td style="padding:10px;border:1px solid #e5e7eb;">{{ $guestName }} &lt;{{ $guestEmail }}&gt;</td>
                            </tr>
                            <tr>
                                <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;">Check-in</td>
                                <td style="padding:10px;border:1px solid #e5e7eb;">{{ $checkin }}</td>
                            </tr>
                            <tr>
                                <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;">Check-out</td>
                                <td style="padding:10px;border:1px solid #e5e7eb;">{{ $checkout }}</td>
                            </tr>
                            @if($total !== null)
                                <tr>
                                    <td style="padding:10px;border:1px solid #e5e7eb;background:#f9fafb;">Booking total</td>
                                    <td style="padding:10px;border:1px solid #e5e7eb;">{{ $currency }} {{ number_format((float) $total, 2) }}</td>
                                </tr>
                            @endif
                        </table>

                        <p style="margin:0;font-size:15px;line-height:1.6;">
                            Check Stripe/PMS records for refund status if payment was collected.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
