<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Activity Evaluation</title>
  <style>
    body { background:#f5f7fb;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial;color:#334155;margin:0;padding:20px; }
    .container { max-width:600px;margin:28px auto; }
    .card { background:#fff;border-radius:10px;box-shadow:0 4px 18px rgba(16,24,40,.06);overflow:hidden; }
    .card-header { background:linear-gradient(90deg,#4f46e5,#6366f1);padding:20px 24px;color:#fff; }
    .card-header h1 { font-size:18px;margin:0 0 4px; }
    .card-header p { margin:0;font-size:13px;opacity:.85; }
    .card-body { padding:24px; }
    .greeting { font-size:15px;margin:0 0 14px; }
    .details { width:100%;border-collapse:collapse;margin:14px 0; }
    .details td { padding:9px 6px;border-bottom:1px solid #f1f5f9;font-size:14px; }
    .label { color:#64748b;width:40%;font-weight:600; }
    .value { color:#0f172a; }
    .btn { display:inline-block;background:#4f46e5;color:#ffffff !important;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;margin:20px 0; }
    .note { background:#eef2ff;border-left:3px solid #6366f1;padding:10px 14px;border-radius:4px;font-size:13px;color:#3730a3;margin:16px 0; }
    .footer { padding:14px 24px;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h1>Activity Evaluation</h1>
        <p>Philippine Science High School — Caraga Region Campus</p>
      </div>
      <div class="card-body">
        <p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
        <p>Thank you for participating in the following activity. Please take a moment to evaluate it using the link below.</p>

        <table class="details" role="presentation">
          <tr>
            <td class="label">Activity</td>
            <td class="value"><strong>{{ $activity->title }}</strong></td>
          </tr>
          <tr>
            <td class="label">Date</td>
            <td class="value">
              {{ \Carbon\Carbon::parse($activity->start_date)->format('F d, Y') }}
              @if($activity->end_date && $activity->end_date != $activity->start_date)
                – {{ \Carbon\Carbon::parse($activity->end_date)->format('F d, Y') }}
              @endif
            </td>
          </tr>
          @if($activity->venue)
          <tr>
            <td class="label">Venue</td>
            <td class="value">{{ $activity->venue }}</td>
          </tr>
          @endif
        </table>

        <div style="text-align:center;">
          <a href="{{ $evaluationUrl }}" class="btn">Evaluate This Activity</a>
        </div>

        <div class="note">
          Your certificate of participation will only be released after you complete the evaluation. The link above is unique to you — please do not share it.
        </div>

        <p style="font-size:13px;color:#64748b;margin-top:16px;">
          This is a system-generated email from the BugSayMIS Activity Management System.
        </p>
      </div>
      <div class="footer">
        Philippine Science High School – Caraga Region Campus &bull; BugSayMIS
      </div>
    </div>
  </div>
</body>
</html>
