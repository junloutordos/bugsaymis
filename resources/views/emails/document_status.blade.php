<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>CRCMIS Document Tracking</title>
  <style>
    body { background:#f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:#334155; margin:0; padding:20px; }
    .container { max-width:600px; margin:28px auto; }
    .card { background:#ffffff; border-radius:10px; box-shadow:0 4px 18px rgba(16,24,40,0.06); overflow:hidden; }
    .card-header { padding:18px 20px; color:#fff; }
    .card-body { padding:20px; }
    h1 { font-size:18px; margin:0 0 4px; }
    .details { width:100%; border-collapse:collapse; margin:12px 0; }
    .details td { padding:8px 6px; border-bottom:1px solid #f1f5f9; font-size:14px; }
    .label { color:#64748b; width:42%; font-weight:600; }
    .value { color:#0f172a; }
    .badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:700; }
    .badge-normal  { background:#dbeafe; color:#1e40af; }
    .badge-urgent  { background:#fef3c7; color:#92400e; }
    .badge-rush    { background:#fee2e2; color:#991b1b; }
    .badge-ext     { background:#f0fdf4; color:#166534; }
    .badge-int     { background:#eff6ff; color:#1d4ed8; }
    .actions { padding:18px 20px; text-align:center; }
    .btn { display:inline-block; color:white; padding:12px 28px; border-radius:8px; text-decoration:none; font-weight:600; font-size:15px; }
    .footer { padding:14px 20px; font-size:12px; color:#94a3b8; border-top:1px solid #f1f5f9; }
    @media (max-width:480px){ .container{padding:8px} .card-body{padding:14px} }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header" style="background:{{ $headerColor ?? '#6366f1' }}">
        <h1>{{ $headline }}</h1>
        <p style="margin:4px 0 0; opacity:.85; font-size:13px;">CRCMIS — Document Tracking System</p>
      </div>

      <div class="card-body">
        <p style="margin:0 0 12px;">Hello <strong>{{ $recipientName }}</strong>,</p>
        <p style="margin:0 0 14px; color:#475569;">{!! $intro !!}</p>

        <table class="details" role="presentation">
          <tr>
            <td class="label">Tracking No.</td>
            <td class="value"><strong>{{ $document->tracking_no }}</strong></td>
          </tr>
          <tr>
            <td class="label">Type</td>
            <td class="value">{{ $document->documentType->name ?? '—' }}</td>
          </tr>
          <tr>
            <td class="label">Subject</td>
            <td class="value">{{ $document->subject }}</td>
          </tr>
          <tr>
            <td class="label">Origin</td>
            <td class="value">
              <span class="badge {{ $document->origin_type === 'external' ? 'badge-ext' : 'badge-int' }}">
                {{ ucfirst($document->origin_type) }}
              </span>
            </td>
          </tr>
          <tr>
            <td class="label">Priority</td>
            <td class="value">
              @php $p = $document->priority ?? 'Normal'; @endphp
              <span class="badge {{ $p === 'Rush' ? 'badge-rush' : ($p === 'Urgent' ? 'badge-urgent' : 'badge-normal') }}">
                {{ $p }}
              </span>
            </td>
          </tr>
          @foreach($extraRows ?? [] as $label => $val)
          <tr>
            <td class="label">{{ $label }}</td>
            <td class="value">{{ $val }}</td>
          </tr>
          @endforeach
          @if($document->deadline_at)
          <tr>
            <td class="label">Deadline</td>
            <td class="value" style="color:#dc2626; font-weight:600;">
              {{ $document->deadline_at->format('F j, Y g:i A') }}
            </td>
          </tr>
          @endif
        </table>

        <p style="font-size:13px; color:#64748b; margin-top:12px;">
          Log in to CRCMIS to view the document, scan, and full routing trail.
        </p>
      </div>

      <div class="actions">
        <a href="{{ $viewUrl }}" class="btn" style="background:{{ $headerColor ?? '#6366f1' }}">
          {{ $btnLabel ?? 'View Document' }}
        </a>
      </div>

      <div class="footer">
        Automated notification from CRCMIS Document Tracking System. Do not reply to this email.
      </div>
    </div>
  </div>
</body>
</html>
