<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Check-out Info — {{ $property->name }}</title>
    <style>
        body { font-family: 'Lato', Arial, sans-serif; color: #333; max-width: 640px; margin: 0 auto; padding: 20px; line-height: 1.55; }
        h2 { color: #62ade4; margin: 0 0 10px 0; }
        .field { margin: 12px 0; }
        .field-label { display: inline-block; min-width: 150px; font-weight: bold; color: #555; }
        hr { border: none; border-top: 1px solid #eee; margin: 24px 0; }
        .checklist-block { background: #f5f9ff; border-left: 4px solid #62ade4; padding: 12px 16px; }
        .notes-block { background: #fffbe6; border-left: 4px solid #f5c518; padding: 12px 16px; }
    </style>
</head>
<body>
    <h2>Check-out reminder — {{ $property->name }}</h2>
    <p>Hi {{ $data->name }}, your stay ends on {{ $data->checkout }}. Thanks for staying with us!</p>

    @if(!empty($p['departure_time']))
        <div class="field"><span class="field-label">Departure time:</span> {{ $p['departure_time'] }}</div>
    @endif

    @if(!empty($p['key_return']))
        <div class="field"><span class="field-label">Key return:</span></div>
        <div>{!! $p['key_return'] !!}</div>
    @endif

    @if(!empty($p['cleaning_checklist']))
        <hr>
        <div class="checklist-block">
            <strong>Before you leave:</strong>
            {!! $p['cleaning_checklist'] !!}
        </div>
    @endif

    @if(!empty($p['notes_html']))
        <hr>
        <div class="notes-block">{!! $p['notes_html'] !!}</div>
    @endif

    <hr>
    <p>Safe travels!</p>
</body>
</html>
