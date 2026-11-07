<!DOCTYPE html>
<html>
<head><title>New Booking Request</title></head>
<body>
<p>You have a new booking request.</p>

<h3>{{ $property->name }}</h3>
<p><strong>Guest:</strong> {{ $data->name }} ({{ $data->email }}{{ $data->mobile ? ' &mdash; ' . $data->mobile : '' }})</p>
<p><strong>Dates:</strong> {{ $data->checkin }} &rarr; {{ $data->checkout }} ({{ $data->total_night ?? '?' }} night{{ ($data->total_night ?? 0) !== 1 ? 's' : '' }})</p>
<p><strong>Guests:</strong> {{ $data->total_guests ?? $data->adults }}</p>
@if(!empty($data->total_pets))
    <p><strong>Pets:</strong> {{ $data->total_pets }}</p>
@endif
<p><strong>Estimated total:</strong> ${{ number_format((float)$data->total_amount, 2) }}</p>

@if(!empty($data->message))
    <p><strong>Message from guest:</strong></p>
    <blockquote>{{ $data->message }}</blockquote>
@endif

<p>To approve this request and send the guest a payment link, log into the admin and click "Approve" on booking #{{ $data->id }}.</p>
</body>
</html>
