<!DOCTYPE html>
<html><body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;">
<table width="100%" cellpadding="20"><tr><td>
    <h2 style="color: #62ade4;">Thank you, {{ $booking->name }}</h2>
    <p>Your signed rental agreement for <strong>{{ $property->name ?? ($property->title ?? 'your stay') }}</strong> is attached to this email.</p>
    <p><strong>Booking summary:</strong></p>
    <ul>
        <li>Check-in: {{ $booking->checkin }}</li>
        <li>Check-out: {{ $booking->checkout }}</li>
        <li>Booking #: {{ $booking->id }}</li>
    </ul>
    <p>Please retain a copy for your records. The attached PDF is the legal artifact of this agreement and contains the signing audit (timestamp, IP, user agent, and the consent statement you accepted).</p>
    <p>If you have any questions, reply to this email.</p>
    <p style="margin-top: 30px; font-size: 12px; color: #999;">Sent via {{ $setting_data['website'] ?? 'our booking system' }}.</p>
</td></tr></table>
</body></html>
