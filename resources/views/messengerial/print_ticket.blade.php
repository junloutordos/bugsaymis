<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Messengerial Request #{{ $request->id }} - Print</title>
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
        <h1>Messengerial Request #{{ $request->id }}</h1>
        <div class="row"><div class="label">Requestor</div><div class="value">{{ $request->requestor }}</div></div>
        <div class="row"><div class="label">Unit</div><div class="value">{{ $request->unit ?? '—' }}</div></div>
        <div class="row"><div class="label">Reference No.</div><div class="value">{{ $request->reference_no ?? '—' }}</div></div>
        <div class="row"><div class="label">Purpose</div><div class="value">{{ $request->purpose ?? '—' }}</div></div>
        <div class="row"><div class="label">Destination</div><div class="value">{{ $request->destination ?? '—' }}</div></div>
        <div class="row"><div class="label">Delivery Methods</div><div class="value">{{ is_array($request->delivery_methods) ? implode(', ', $request->delivery_methods) : ($request->delivery_methods ?: '—') }}</div></div>
        <div class="row"><div class="label">Kind</div><div class="value">{{ is_array($request->messengerial_kinds) ? implode(', ', $request->messengerial_kinds) : ($request->messengerial_kinds ?: '—') }}</div></div>
        <div class="row"><div class="label">Consignee</div><div class="value">{{ $request->consignee_name ?? '—' }} {{ $request->consignee_contact ? '(' . $request->consignee_contact . ')' : '' }}</div></div>
        <div class="row"><div class="label">Consignee Email</div><div class="value">{{ $request->consignee_email ?? '—' }}</div></div>
        <div class="row"><div class="label">Status</div><div class="value"><span class="badge">{{ $request->status }}</span></div></div>
        <div style="margin-top:24px; font-size:12px; color:#666;">Printed: {{ now() }}</div>
    </div>
</body>
</html>
