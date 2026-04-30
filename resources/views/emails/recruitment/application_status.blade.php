<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $subject }}</title>
  <style>
    body { background:#f5f7fb; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial; color:#334155; margin:0; padding:20px; }
    .container { max-width:600px; margin:28px auto; }
    .card { background:#ffffff; border-radius:10px; box-shadow:0 4px 18px rgba(16,24,40,0.06); overflow:hidden; }
    .card-header { padding:18px 20px; color:#fff; }
    .card-header.positive { background:linear-gradient(90deg,#10b981,#059669); }
    .card-header.warning  { background:linear-gradient(90deg,#f59e0b,#d97706); }
    .card-header.negative { background:linear-gradient(90deg,#ef4444,#dc2626); }
    .card-header.neutral  { background:linear-gradient(90deg,#3b82f6,#2563eb); }
    .card-body { padding:24px; }
    h1 { font-size:18px; margin:0 0 4px; }
    p.lead { margin:0; font-size:13px; opacity:0.85; }
    p { margin:0 0 12px; color:#475569; }
    .badge { display:inline-block; padding:4px 12px; border-radius:20px; font-size:13px; font-weight:600; text-transform:capitalize; margin-bottom:16px; }
    .badge-screening  { background:#dbeafe; color:#1d4ed8; }
    .badge-exam       { background:#fef3c7; color:#92400e; }
    .badge-interview  { background:#ede9fe; color:#6d28d9; }
    .badge-ranking    { background:#d1fae5; color:#065f46; }
    .badge-selection  { background:#cffafe; color:#0e7490; }
    .badge-placement  { background:#d1fae5; color:#065f46; }
    .badge-rejected   { background:#fee2e2; color:#991b1b; }
    .badge-withdrawn  { background:#f3f4f6; color:#6b7280; }
    .details { width:100%; border-collapse:collapse; margin:12px 0; }
    .details td { padding:8px 6px; border-bottom:1px solid #f1f5f9; font-size:14px; }
    .label { color:#64748b; width:42%; font-weight:600; }
    .value { color:#0f172a; }
    .remarks-box { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:12px 16px; font-size:14px; color:#475569; margin-top:12px; }
    .footer { padding:14px 20px; font-size:13px; color:#94a3b8; border-top:1px solid #f1f5f9; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      @php
        $headerClass = match($stage) {
            'placement','ranking','selection' => 'positive',
            'exam','interview'                => 'warning',
            'rejected','withdrawn'            => 'negative',
            default                           => 'neutral',
        };
      @endphp
      <div class="card-header {{ $headerClass }}">
        <h1>{{ $subject }}</h1>
        <p class="lead">CRCMIS — Recruitment &amp; Selection</p>
      </div>
      <div class="card-body">
        <p>Dear <strong>{{ $applicant?->full_name ?? 'Applicant' }}</strong>,</p>
        <p>We would like to inform you that the status of your application has been updated.</p>

        <span class="badge badge-{{ $stage }}">{{ str_replace('_', ' ', $stage) }}</span>

        <table class="details" role="presentation">
          <tr>
            <td class="label">Position</td>
            <td class="value">{{ $position }}</td>
          </tr>
          <tr>
            <td class="label">Application No.</td>
            <td class="value">#{{ $application->id }}</td>
          </tr>
          <tr>
            <td class="label">Current Stage</td>
            <td class="value" style="text-transform:capitalize;">{{ str_replace('_', ' ', $stage) }}</td>
          </tr>
          <tr>
            <td class="label">Date Updated</td>
            <td class="value">{{ now()->format('F j, Y') }}</td>
          </tr>
        </table>

        @if($remarks)
          <div class="remarks-box">
            <strong>Remarks:</strong> {{ $remarks }}
          </div>
        @endif

        @if($stage === 'rejected')
          <p style="margin-top:16px;">We appreciate the time you invested in our selection process and encourage you to apply for future opportunities.</p>
        @elseif($stage === 'placement')
          <p style="margin-top:16px;">Congratulations! Please report to the HR Office to complete your onboarding requirements.</p>
        @else
          <p style="margin-top:16px;">You will be notified of further updates. If you have questions, please contact the HR Office.</p>
        @endif
      </div>
      <div class="footer">
        This is an automated message from CRCMIS. Please do not reply directly to this email.
      </div>
    </div>
  </div>
</body>
</html>
