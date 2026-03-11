<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { background:#f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:#334155; margin:0; padding:20px; }
        .container { max-width:600px; margin:28px auto; }
        .card { background:#fff; border-radius:10px; box-shadow:0 4px 18px rgba(16,24,40,0.06); overflow:hidden; }
        .card-header { background:linear-gradient(90deg,#d97706,#f59e0b); padding:20px 24px; color:#fff; }
        .card-header h1 { font-size:18px; margin:0 0 4px; }
        .card-header p { margin:0; font-size:13px; opacity:.85; }
        .card-body { padding:24px; }
        .details { width:100%; border-collapse:collapse; margin:14px 0; }
        .details td { padding:8px 6px; border-bottom:1px solid #f1f5f9; font-size:14px; }
        .label { color:#64748b; width:42%; font-weight:600; }
        .value { color:#0f172a; }
        .badge { display:inline-block; padding:2px 10px; border-radius:9999px; font-size:12px; font-weight:600; background:#fef3c7; color:#92400e; }
        .btn { display:inline-block; background:#d97706; color:#fff; padding:11px 22px; border-radius:8px; text-decoration:none; font-weight:600; font-size:14px; margin-top:16px; }
        .footer { padding:14px 24px; font-size:12px; color:#94a3b8; border-top:1px solid #f1f5f9; }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <h1>IPCR Targets Returned for Revision</h1>
            <p>Individual Performance Commitment and Review</p>
        </div>
        <div class="card-body">
            <p>Dear {{ $recipientName }},</p>
            <p>Your IPCR targets have been <strong>returned for revision</strong> by your Division Chief. Please log in to {{ config('app.name') }} to review the feedback and update your targets accordingly.</p>
            <table class="details" role="presentation">
                <tr><td class="label">Rating Period</td><td class="value">{{ $ipcr->rating_period }}</td></tr>
                <tr><td class="label">Title</td><td class="value">{{ $ipcr->title }}</td></tr>
                <tr><td class="label">Status</td><td class="value"><span class="badge">{{ $ipcr->status }}</span></td></tr>
                @if($ipcr->remarks)
                <tr><td class="label">Remarks</td><td class="value">{{ $ipcr->remarks }}</td></tr>
                @endif
                <tr><td class="label">Date</td><td class="value">{{ now()->format('F j, Y, g:i A') }}</td></tr>
            </table>
            <a class="btn" href="{{ route('employee-ipcr.show', $ipcr->id) }}">View IPCR</a>
        </div>
        <div class="footer">This is an automated notification from {{ config('app.name') }}. Please do not reply to this email.</div>
    </div>
</div>
</body>
</html>
