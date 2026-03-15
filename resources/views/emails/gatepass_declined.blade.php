<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Gate Pass Declined</title>
  <style>
    body { background:#f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:#334155; margin:0; padding:20px; }
    .container { max-width:600px; margin:28px auto; }
    .card { background:#ffffff; border-radius:10px; box-shadow:0 4px 18px rgba(16,24,40,0.06); overflow:hidden; }
    .card-header { background:linear-gradient(90deg,#ef4444,#dc2626); padding:18px 20px; color:#fff; }
    .card-body { padding:20px; }
    h1 { font-size:18px; margin:0 0 6px; }
    p.lead { margin:0 0 12px; color:#475569; }
    .details { width:100%; border-collapse:collapse; margin:12px 0; }
    .details td { padding:8px 6px; border-bottom:1px solid #f1f5f9; }
    .label { color:#64748b; width:42%; font-weight:600; }
    .value { color:#0f172a; }
    .muted { color:#94a3b8; font-size:13px; }
    .footer { padding:14px 20px; font-size:13px; color:#94a3b8; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h1>Gate Pass — Request Declined</h1>
      </div>
      <div class="card-body">
        <p class="lead">Hello {{ $gatepass->name ?? ($gatepass->employee_name ?? 'Requester') }},</p>
        <p>Your gate pass request has been declined by your Division Chief.</p>

        <table class="details" role="presentation">
          <tr>
            <td class="label">Control No</td>
            <td class="value">{{ $gatepass->controlno ?? '—' }}</td>
          </tr>
          <tr>
            <td class="label">Date</td>
            <td class="value">{{ $gatepass->gatepass_date ?? '—' }}</td>
          </tr>
          <tr>
            <td class="label">Badge</td>
            <td class="value">{{ $gatepass->badgeNumber ?? '—' }}</td>
          </tr>
          <tr>
            <td class="label">Reason</td>
            <td class="value">{{ $reason ?? '—' }}</td>
          </tr>
        </table>

        <p class="muted">If you have questions about this decision, please contact your Division Chief.</p>
      </div>
      <div class="footer">Thanks — BUGSAYMIS</div>
    </div>
  </div>
</body>
</html>
