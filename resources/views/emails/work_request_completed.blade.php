<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Work Request Completed</title>
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
    .footer { padding:14px 20px; font-size:13px; color:#94a3b8; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h1>Work Request Completed</h1>
      </div>
      <div class="card-body">
        <p class="lead">Hello {{ $request->requester?->name ?? 'Requester' }},</p>
        <p>Your work request (ID: {{ $request->id }}) has been completed. Below are the details:</p>

        <div class="details">
          <table role="presentation">
            <tr>
              <td class="label">Action Taken</td>
              <td class="value">{{ $request->action_taken ?? '—' }}</td>
            </tr>
            <tr>
              <td class="label">Acted By</td>
              <td class="value">{{ $request->actedBy?->name ?? '—' }}</td>
            </tr>
            <tr>
              <td class="label">Date Completed</td>
              <td class="value">
                @php
                  $completedDate = null;
                  try {
                    if (!empty($request->date_completed)) {
                      $completedDate = \Carbon\Carbon::parse($request->date_completed)->format('F d, Y');
                    }
                  } catch (\Throwable $e) {
                    $completedDate = null;
                  }
                @endphp
                {{ $completedDate ?? '—' }}
              </td>
            </tr>
          </table>
        </div>
      </div>
      <div class="footer">Thanks — BUGSAYMIS</div>
    </div>
  </div>
</body>
</html>
