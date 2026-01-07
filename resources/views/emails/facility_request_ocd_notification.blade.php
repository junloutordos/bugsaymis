<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>OCD Action Required</title>
  <style>
    body { background:#f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial; color:#334155; margin:0; padding:20px; }
    .container { max-width:600px; margin:28px auto; }
    .card { background:#ffffff; border-radius:10px; box-shadow:0 4px 18px rgba(16,24,40,0.06); overflow:hidden; }
    .card-header { background:linear-gradient(90deg,#6366f1,#3b82f6); padding:18px 20px; color:#fff; }
    .card-body { padding:20px; }
    h1 { font-size:18px; margin:0 0 6px; }
    p.lead { margin:0 0 12px; color:#475569; }
    .details { width:100%; border-collapse:collapse; margin:12px 0; }
    .details td { padding:8px 6px; border-bottom:1px solid #f1f5f9; }
    .label { color:#64748b; width:42%; font-weight:600; }
    .value { color:#0f172a; }
    .actions { padding:18px 20px; text-align:center; }
    .btn { display:inline-block; background:#10b981; color:white; padding:12px 18px; border-radius:8px; text-decoration:none; font-weight:600; }
    .muted { color:#94a3b8; font-size:13px; }
    .footer { padding:14px 20px; font-size:13px; color:#94a3b8; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h1>Facility Request — OCD Action Needed</h1>
      </div>
      <div class="card-body">
        <p class="lead">Hello,</p>
        <p>A facility request has been approved by the division chief and requires OCD action.</p>

        <table class="details" role="presentation">
          <tr>
            <td class="label">Request ID</td>
            <td class="value">{{ $request->id }}</td>
          </tr>
          <tr>
            <td class="label">Activity / Purpose</td>
            <td class="value">{{ $request->activity }}{{ $request->purpose ? ' — ' . $request->purpose : '' }}</td>
          </tr>
        </table>

        <div class="actions">
          <a class="btn" href="{{ $approveUrl }}">Approve Facility Request</a>
          @if(!empty($declineUrl))
            <a class="btn" style="background:#ef4444;margin-left:8px;" href="{{ $declineUrl }}">Decline</a>
          @endif
        </div>

        <p class="muted">If the button above does not work, copy and paste the following link into your browser:</p>
        <p class="muted"><a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>
      </div>
      <div class="footer">If you do not have permission to act on this request, you may ignore this email.<br>Thanks — BUGSAYMIS</div>
    </div>
  </div>
</body>
</html>
