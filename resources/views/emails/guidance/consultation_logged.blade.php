<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>New Guidance Consultation</title>
  <style>
    body { background:#f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:#334155; margin:0; padding:20px; }
    .container { max-width:600px; margin:28px auto; }
    .card { background:#ffffff; border-radius:10px; box-shadow:0 4px 18px rgba(16,24,40,0.06); overflow:hidden; }
    .card-header { background:linear-gradient(90deg,#0ea5e9,#3b82f6); padding:18px 20px; color:#fff; }
    .card-body { padding:20px; }
    h1 { font-size:18px; margin:0 0 6px; }
    p.lead { margin:0 0 12px; color:#475569; }
    .details { width:100%; border-collapse:collapse; margin:12px 0; }
    .details td { padding:8px 6px; border-bottom:1px solid #f1f5f9; }
    .label { color:#64748b; width:42%; font-weight:600; }
    .value { color:#0f172a; }
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
        <h1>Guidance Consultation — New Entry</h1>
      </div>
      <div class="card-body">
        <p class="lead">Hello,</p>
        <p>A new guidance consultation was scheduled via the kiosk. Details are below.</p>

        <table class="details" role="presentation">
          <tr>
            <td class="label">Request ID</td>
            <td class="value">{{ $consult->id }}</td>
          </tr>
          <tr>
            <td class="label">Requestor</td>
            <td class="value">{{ $requestor->name ?? ($consult->requestor ?? '—') }}</td>
          </tr>
          <tr>
            <td class="label">Date Preferred</td>
            <td class="value">{{ $consult->date_time_preferred ?? '—' }}</td>
          </tr>
          <tr>
            <td class="label">Concern</td>
            <td class="value">{{ $consult->concern ?? '—' }}</td>
          </tr>
          <tr>
            <td class="label">Details</td>
            <td class="value">{{ $consult->description ?? '—' }}</td>
          </tr>
        </table>

        <div class="actions">
          <a class="btn" href="{{ route('guidance.consultations.index') }}">View Consultations</a>
        </div>

        <p class="muted">If the button above does not work, open the application and go to Guidance → Consultations.</p>
      </div>
      <div class="footer">If you do not handle guidance consultations, you may ignore this email.<br>Thanks — BUGSAYMIS</div>
    </div>
  </div>
</body>
</html>
