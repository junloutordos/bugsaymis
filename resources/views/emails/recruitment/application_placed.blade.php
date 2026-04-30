<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Congratulations — Your Placement Has Been Confirmed!</title>
  <style>
    body { background:#f5f7fb; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial; color:#334155; margin:0; padding:20px; }
    .container { max-width:600px; margin:28px auto; }
    .card { background:#ffffff; border-radius:10px; box-shadow:0 4px 18px rgba(16,24,40,0.06); overflow:hidden; }
    .card-header { background:linear-gradient(90deg,#10b981,#059669); padding:18px 20px; color:#fff; }
    .card-body { padding:24px; }
    h1 { font-size:18px; margin:0 0 4px; }
    p.lead { margin:0; font-size:13px; opacity:0.85; }
    p { margin:0 0 12px; color:#475569; }
    .congrats-banner { background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:16px; text-align:center; margin-bottom:20px; }
    .congrats-banner .icon { font-size:36px; }
    .congrats-banner h2 { font-size:18px; color:#065f46; margin:8px 0 4px; }
    .congrats-banner p { font-size:14px; color:#047857; margin:0; }
    .details { width:100%; border-collapse:collapse; margin:12px 0; }
    .details td { padding:9px 6px; border-bottom:1px solid #f1f5f9; font-size:14px; }
    .label { color:#64748b; width:42%; font-weight:600; }
    .value { color:#0f172a; }
    .remarks-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; font-size:14px; color:#475569; margin-top:12px; }
    .footer { padding:14px 20px; font-size:13px; color:#94a3b8; border-top:1px solid #f1f5f9; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h1>Placement Confirmation</h1>
        <p class="lead">CRCMIS — Recruitment &amp; Selection</p>
      </div>
      <div class="card-body">
        <div class="congrats-banner">
          <div class="icon">🎉</div>
          <h2>Congratulations, {{ $applicant?->first_name ?? 'Applicant' }}!</h2>
          <p>Your selection has been approved and your placement has been confirmed.</p>
        </div>

        <table class="details" role="presentation">
          <tr>
            <td class="label">Name</td>
            <td class="value">{{ $applicant?->full_name ?? '—' }}</td>
          </tr>
          <tr>
            <td class="label">Position</td>
            <td class="value">{{ $position }}</td>
          </tr>
          <tr>
            <td class="label">Assigned Office</td>
            <td class="value">{{ $office }}</td>
          </tr>
          <tr>
            <td class="label">Start Date</td>
            <td class="value">{{ $start_date }}</td>
          </tr>
          @if($end_date)
          <tr>
            <td class="label">End Date</td>
            <td class="value">{{ $end_date }}</td>
          </tr>
          @endif
          <tr>
            <td class="label">Placement Ref.</td>
            <td class="value">#{{ $placement->id }}</td>
          </tr>
        </table>

        @if($remarks)
          <div class="remarks-box">
            <strong>Remarks from HR:</strong> {{ $remarks }}
          </div>
        @endif

        <p style="margin-top:20px;">Please report to the <strong>HR Office</strong> on or before your start date to complete your onboarding requirements. Bring the necessary documents as advised.</p>
        <p>If you have any questions or concerns, do not hesitate to contact the HR Department.</p>
      </div>
      <div class="footer">
        This is an automated message from CRCMIS. Please do not reply directly to this email.
      </div>
    </div>
  </div>
</body>
</html>
