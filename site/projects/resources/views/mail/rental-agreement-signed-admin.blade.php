<!DOCTYPE html>
<html><body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;">
<table width="100%" cellpadding="20"><tr><td>
    <h2 style="color: #62ade4;">Rental Agreement Signed — Booking #{{ $booking->id }}</h2>
    <p><strong>{{ $booking->name }}</strong> ({{ $booking->email }}) has signed the rental agreement for <strong>{{ $property->name ?? ($property->title ?? 'Property #'.$property->id) }}</strong>.</p>
    <table style="border-collapse: collapse; margin: 12px 0;">
        <tr><td style="padding: 4px 12px;"><strong>Check-in:</strong></td><td>{{ $booking->checkin }}</td></tr>
        <tr><td style="padding: 4px 12px;"><strong>Check-out:</strong></td><td>{{ $booking->checkout }}</td></tr>
        <tr><td style="padding: 4px 12px;"><strong>Signed at:</strong></td><td>{{ $booking->rental_agreement_signed_at }}</td></tr>
        <tr><td style="padding: 4px 12px;"><strong>IP:</strong></td><td>{{ $booking->rental_agreement_signed_ip }}</td></tr>
        <tr><td style="padding: 4px 12px;"><strong>Total:</strong></td><td>{{ $setting_data['payment_currency'] ?? 'USD' }} {{ $booking->total_amount }}</td></tr>
    </table>
    <p>The signed PDF is attached. The full audit (user agent, verbatim consent text) is inside the PDF.</p>
</td></tr></table>
</body></html>
