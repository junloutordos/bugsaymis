<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Messengerial Request Status</title>
    <style>
        body { background:#f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial; color:#334155; margin:0; padding:20px; }
        .container { max-width:640px; margin:28px auto; }
        .card { background:#ffffff; border-radius:10px; box-shadow:0 4px 18px rgba(16,24,40,0.06); overflow:hidden; }
        .card-header { background:linear-gradient(90deg,#10b981,#059669); padding:18px 20px; color:#fff; }
        .card-body { padding:20px; }
        h1 { font-size:18px; margin:0 0 6px; }
        .lead { margin:0 0 12px; color:#475569; }
        .details { width:100%; border-collapse:collapse; margin:12px 0; }
        .details td { padding:8px 6px; border-bottom:1px solid #f1f5f9; }
        .label { color:#64748b; width:42%; font-weight:600; }
        .value { color:#0f172a; }
        .status { display:inline-block; padding:8px 12px; border-radius:8px; font-weight:600; }
        .status.approved { background:#bbf7d0; color:#166534; }
        .status.declined { background:#fecaca; color:#991b1b; }
        .actions { padding:18px 20px; text-align:center; }
        .btn { display:inline-block; background:#3b82f6; color:white; padding:12px 18px; border-radius:8px; text-decoration:none; font-weight:600; }
        .muted { color:#94a3b8; font-size:13px; }
        .footer { padding:14px 20px; font-size:13px; color:#94a3b8; }
        @media (max-width:480px){ .container{padding:12px} .card-body{padding:14px} }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h1>Messengerial Request — {{ $status }}</h1>
            </div>
            <div class="card-body">
                <p class="lead">Hello {{ $request->requestor ?? 'Requestor' }},</p>

                @if(!empty($approver) && str_contains(strtolower($status), 'approved'))
                    <p><strong>This request was approved by {{ $approver }}.</strong></p>
                @elseif(!empty($approver) && str_contains(strtolower($status), 'declined'))
                    <p><strong>This request was declined by {{ $approver }}.</strong></p>
                @endif

                <table class="details" role="presentation">
                    <tr>
                        <td class="label">Request ID</td>
                        <td class="value">{{ $request->id }}</td>
                    </tr>
                    <tr>
                        <td class="label">Requesting Personnel</td>
                        <td class="value">{{ $request->requestor ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Unit</td>
                        <td class="value">{{ $request->unit ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Reference No.</td>
                        <td class="value">{{ $request->reference_no ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Purpose</td>
                        <td class="value">{{ $request->purpose ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Destination</td>
                        <td class="value">{{ $request->destination ?? '—' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Delivery Methods</td>
                        <td class="value">@if(is_array($request->delivery_methods)) {{ implode(', ', $request->delivery_methods) }} @else {{ $request->delivery_methods ?? '—' }} @endif</td>
                    </tr>
                    <tr>
                        <td class="label">Kind</td>
                        <td class="value">@if(is_array($request->messengerial_kinds)) {{ implode(', ', $request->messengerial_kinds) }} @else {{ $request->messengerial_kinds ?? '—' }} @endif</td>
                    </tr>
                    <tr>
                        <td class="label">Consignee</td>
                        <td class="value">{{ $request->consignee_name ?? '—' }} {{ $request->consignee_contact ? '(' . $request->consignee_contact . ')' : '' }}</td>
                    </tr>
                    <tr>
                        <td class="label">Consignee Email</td>
                        <td class="value">{{ $request->consignee_email ?? '—' }}</td>
                    </tr>
                </table>

                <div style="text-align:center;margin-bottom:12px;">
                    <span class="status {{ str_contains(strtolower($status), 'approved') ? 'approved' : 'declined' }}">{{ $status }}</span>
                </div>

                @if(!empty($reason) && strtolower($status) === 'declined')
                    <p><strong>Reason:</strong> {{ $reason }}</p>
                @endif

                <div class="actions">
                    <a class="btn" href="{{ url('/') }}">Return to Application</a>
                </div>

                <p class="muted">If the button above does not work, copy and paste the following link into your browser:</p>
                <p class="muted"><a href="{{ url('/') }}">{{ url('/') }}</a></p>
            </div>
            <div class="footer">Thanks — BUGSAYMIS</div>
        </div>
    </div>
</body>
</html>
