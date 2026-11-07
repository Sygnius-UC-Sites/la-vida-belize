<!DOCTYPE html>
<html>
<head><title>Booking Request Received</title></head>
<body>
<p>Hi {{ $data->name }},</p>

<p>Thank you for your booking request for <strong>{{ $property->name }}</strong>. We've received your request and the property owner will get back to you within 24 hours.</p>

<p><strong>Your request details:</strong></p>
<ul>
    <li>Property: {{ $property->name }}</li>
    <li>Check-in: {{ $data->checkin }}</li>
    <li>Check-out: {{ $data->checkout }}</li>
    <li>Guests: {{ $data->total_guests ?? $data->adults }}</li>
    <li>Estimated total: ${{ number_format((float)$data->total_amount, 2) }}</li>
    <li>Request #: {{ $data->id }}</li>
</ul>

<p>Once approved, you'll receive an email with a payment link to confirm and complete your booking.</p>

<p>If you have any questions in the meantime, please reply to this email.</p>

<p>Thanks,<br>The {{ $setting_data['site_name'] ?? 'Property' }} team</p>
</body>
</html>
