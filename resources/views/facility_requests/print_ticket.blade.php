<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Facility Request #{{ $request->id }} - Print</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        h1 { font-size: 18px; margin-bottom: 8px; }
        .row { display:flex; gap:12px; margin-bottom:8px; }
        .label { width:160px; font-weight:600; }
        .value { flex:1; }
        .badge { display:inline-block; padding:4px 8px; border-radius:4px; background:#eef; }
    </style>
</head>
<body onload="window.print()">
    <div class="container">
        <h1>Facility Request #{{ $request->id }}</h1>
        <div class="row"><div class="label">Requestor</div><div class="value">{{ $request->requestor }}</div></div>
        <div class="row"><div class="label">Unit</div><div class="value">{{ $request->unit ?? '—' }}</div></div>
        <div class="row"><div class="label">Activity</div><div class="value">{{ $request->activity ?? '—' }}</div></div>
        <div class="row"><div class="label">Purpose</div><div class="value">{{ $request->purpose ?? '—' }}</div></div>
        <div class="row"><div class="label">Date(s)</div><div class="value">{{ $request->date_start }} @if($request->date_end) — {{ $request->date_end }} @endif</div></div>
        <div class="row"><div class="label">Time(s)</div><div class="value">{{ $request->time_start ?? '—' }} @if($request->time_end) — {{ $request->time_end }} @endif</div></div>
        <div class="row"><div class="label">Venue</div><div class="value">
            @php
                $venues = $request->venue ?? [];
                if (!is_array($venues) && $venues) $venues = [$venues];
            @endphp
            {{ implode(', ', $venues) ?: '—' }}
        </div></div>
        <div class="row"><div class="label">Equipment</div><div class="value">{{ is_array($request->equipment) ? implode(', ', $request->equipment) : ($request->equipment ?: '—') }}</div></div>
        <div class="row"><div class="label">Status</div><div class="value"><span class="badge">{{ $request->status }}</span></div></div>
        <div style="margin-top:24px; font-size:12px; color:#666;">Printed: {{ now() }}</div>
    </div>
</body>
</html>
