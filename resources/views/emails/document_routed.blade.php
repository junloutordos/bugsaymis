<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Document Routed to You</title>
  <style>
    body { background:#f5f7fb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:#334155; margin:0; padding:20px; }
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
    .badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:700; }
    .badge-normal   { background:#dbeafe; color:#1e40af; }
    .badge-urgent   { background:#fef3c7; color:#92400e; }
    .badge-very     { background:#fee2e2; color:#991b1b; }
    .badge-conf     { background:#f3e8ff; color:#6b21a8; }
    .actions { padding:18px 20px; text-align:center; }
    .btn { display:inline-block; background:#4f46e5; color:white; padding:12px 24px; border-radius:8px; text-decoration:none; font-weight:600; }
    .footer { padding:14px 20px; font-size:13px; color:#94a3b8; border-top:1px solid #f1f5f9; }
    .due-warning { background:#fffbeb; border:1px solid #fcd34d; border-radius:6px; padding:10px 14px; margin:12px 0; font-size:13px; color:#92400e; }
    @media (max-width:480px){ .container{padding:12px} .card-body{padding:14px} }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h1>{{ $actionType === 'new' ? 'New Document Routed to You' : 'Document Forwarded to You' }}</h1>
        <p style="margin:4px 0 0; opacity:.85; font-size:14px;">BugsayMIS Document Tracking</p>
      </div>

      <div class="card-body">
        <p class="lead">Hello {{ $routing->receiver->name }},</p>
        <p>
          @if($actionType === 'new')
            A new document has been routed to you by <strong>{{ $routing->sender->name }}</strong> for your action.
          @else
            A document has been forwarded to you by <strong>{{ $routing->sender->name }}</strong>.
          @endif
        </p>

        <table class="details" role="presentation">
          <tr>
            <td class="label">Tracking No.</td>
            <td class="value"><strong>{{ $routing->document->tracking_no }}</strong></td>
          </tr>
          <tr>
            <td class="label">Document Type</td>
            <td class="value">{{ $routing->document->documentType->name ?? '—' }} ({{ $routing->document->documentType->code ?? '' }})</td>
          </tr>
          <tr>
            <td class="label">Subject</td>
            <td class="value">{{ $routing->document->subject }}</td>
          </tr>
          <tr>
            <td class="label">Urgency</td>
            <td class="value">
              @php $u = $routing->document->urgency; @endphp
              <span class="badge {{ $u === 'Very Urgent' ? 'badge-very' : ($u === 'Urgent' ? 'badge-urgent' : 'badge-normal') }}">
                {{ $u }}
              </span>
            </td>
          </tr>
          <tr>
            <td class="label">Confidential</td>
            <td class="value">{{ $routing->document->is_confidential ? 'Yes — Handle with care' : 'No' }}</td>
          </tr>
          <tr>
            <td class="label">Routing Step</td>
            <td class="value">Step #{{ $routing->sequence }}</td>
          </tr>
          @if($routing->remarks)
          <tr>
            <td class="label">Remarks from Sender</td>
            <td class="value">{{ $routing->remarks }}</td>
          </tr>
          @endif
          <tr>
            <td class="label">Date Routed</td>
            <td class="value">{{ $routing->created_at->format('F j, Y g:i A') }}</td>
          </tr>
        </table>

        @if($routing->due_at)
        <div class="due-warning">
          ⏰ <strong>Action Required By:</strong>
          {{ $routing->due_at->format('F j, Y g:i A') }}
          ({{ $routing->document->documentType->lead_time_hours }} hours lead time per ARTA guidelines)
        </div>
        @endif

        <p style="font-size:13px; color:#64748b; margin-top:12px;">
          Please log in to BugsayMIS to view the full document, record your action, and forward it to the next recipient as needed.
        </p>
      </div>

      <div class="actions">
        <a href="{{ $viewUrl }}" class="btn">View Document in System</a>
      </div>

      <div class="footer">
        This is an automated notification from BugsayMIS Document Tracking.
        Do not reply to this email.
      </div>
    </div>
  </div>
</body>
</html>
