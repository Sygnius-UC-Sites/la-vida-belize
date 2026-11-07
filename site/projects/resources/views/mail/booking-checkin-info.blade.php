<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Check-in Info — {{ $property->name }}</title>
    <style>
        body { font-family: 'Lato', Arial, sans-serif; color: #333; max-width: 640px; margin: 0 auto; padding: 20px; line-height: 1.55; }
        h2 { color: #62ade4; margin: 0 0 10px 0; }
        .field { margin: 12px 0; }
        .field-label { display: inline-block; min-width: 150px; font-weight: bold; color: #555; }
        code { background: #f0f0f0; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        hr { border: none; border-top: 1px solid #eee; margin: 24px 0; }
        .notes-block { background: #fffbe6; border-left: 4px solid #f5c518; padding: 12px 16px; }
    </style>
</head>
<body>
    <h2>Welcome to {{ $property->name }}</h2>
    <p>Hi {{ $data->name }}, your stay starts today, {{ $data->checkin }}.</p>

    @if(!empty($p['arrival_window']))
        <div class="field"><span class="field-label">Arrival window:</span> {{ $p['arrival_window'] }}</div>
    @endif

    @if(!empty($p['door_code']))
        <div class="field"><span class="field-label">Door code:</span> <code>{{ $p['door_code'] }}</code></div>
    @endif

    @if(!empty($p['wifi_name']))
        <div class="field"><span class="field-label">Wi-Fi:</span> {{ $p['wifi_name'] }}
            @if(!empty($p['wifi_password'])) — password <code>{{ $p['wifi_password'] }}</code>@endif
        </div>
    @endif

    @if(!empty($p['parking_instructions']))
        <div class="field"><span class="field-label">Parking:</span></div>
        <div>{!! $p['parking_instructions'] !!}</div>
    @endif

    @if(!empty($p['host_name']) || !empty($p['host_phone']))
        <div class="field"><span class="field-label">Your host:</span>
            {{ $p['host_name'] }}
            @if(!empty($p['host_phone'])) — <a href="tel:{{ $p['host_phone'] }}">{{ $p['host_phone'] }}</a>@endif
        </div>
    @endif

    @if(!empty($p['emergency_phone']))
        <div class="field"><span class="field-label">Emergency:</span> {{ $p['emergency_phone'] }}</div>
    @endif

    @if(!empty($p['notes_html']))
        <hr>
        <div class="notes-block">{!! $p['notes_html'] !!}</div>
    @endif

    <hr>
    <p>If anything is unclear, reply to this email or contact your host directly.</p>
    <p>Enjoy your stay!</p>
</body>
</html>
