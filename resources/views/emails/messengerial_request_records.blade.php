<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Messengerial Request — Action Required</title>
  <style>
    body { background:#f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial; color:#334155; margin:0; padding:20px; }
    .container { max-width:640px; margin:28px auto; }
    .card { background:#ffffff; border-radius:10px; box-shadow:0 4px 18px rgba(16,24,40,0.06); overflow:hidden; }
    .card-header { background:linear-gradient(90deg,#f97316,#f97316); padding:18px 20px; color:#fff; }
    .card-body { padding:20px; }
    h1 { font-size:18px; margin:0 0 6px; }
    .lead { margin:0 0 12px; color:#475569; }
    .details { width:100%; border-collapse:collapse; margin:12px 0; }
    .details td { padding:8px 6px; border-bottom:1px solid #f1f5f9; }
    .label { color:#64748b; width:42%; font-weight:600; }
    .value { color:#0f172a; }
    .actions { padding:18px 20px; text-align:center; }
    .btn { display:inline-block; background:#3b82f6; color:white; padding:12px 18px; border-radius:8px; text-decoration:none; font-weight:600; }
    .muted { color:#94a3b8; font-size:13px; }
    .footer { padding:14px 20px; font-size:13px; color:#94a3b8; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h1>Messengerial Request — Action Required</h1>
      </div>
      <div class="card-body">
        <p class="lead">Hello Records Team,</p>
        <p>A messengerial request was approved by the Division Chief and requires your action in the system.</p>

        <table class="details" role="presentation">
          <tr>
            <td class="label">Request ID</td>
            <td class="value">{{ $request->id }}</td>
          </tr>
          <tr>
            <td class="label">Requestor</td>
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
        </table>

        <div class="actions">
          <a class="btn" href="{{ $processUrl ?? url('/messengerial') }}">Process Request (Login Required)</a>
        </div>

        <p class="muted">You must login to the application to process this approved request.</p>
      </div>
      <div class="footer">Thanks — BUGSAYMIS</div>
    </div>
  </div>
</body>
</html>
