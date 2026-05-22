<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    body { background:#f5f7fb; font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial; color:#334155; margin:0; padding:20px; }
    .container { max-width:600px; margin:28px auto; }
    .card { background:#fff; border-radius:10px; box-shadow:0 4px 18px rgba(16,24,40,.06); overflow:hidden; }
    .card-header { background:linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%); padding:18px 20px; color:#fff; }
    h1 { font-size:17px; margin:0 0 4px; }
    .card-body { padding:20px; }
    table.meta { width:100%; border-collapse:collapse; margin:12px 0; font-size:13px; }
    table.meta td { padding:7px 6px; border-bottom:1px solid #f1f5f9; }
    .label { color:#64748b; width:38%; font-weight:600; }
    .actions { padding:16px 20px; text-align:center; }
    .btn { display:inline-block; background:#1447c0; color:#fff; padding:11px 24px; border-radius:8px; text-decoration:none; font-weight:600; font-size:14px; margin:4px; }
    .btn-verify { background:#0093b8; }
    .footer { padding:12px 20px; font-size:12px; color:#94a3b8; border-top:1px solid #f1f5f9; }
    .badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:700; background:#dbeafe; color:#1e40af; }
  </style>
</head>
<body>
  <div class="container">
    <div class="card">
      <div class="card-header">
        <h1>Official Issuance — {{ $issuance->type_label }}</h1>
        <p style="margin:0;opacity:.8;font-size:13px;">Philippine Science High School – Caraga Region Campus</p>
      </div>
      <div class="card-body">
        <p>Dear <strong>{{ $recipientName }}</strong>,</p>
        <p>A new official issuance has been released and addressed to you. Please review and acknowledge receipt.</p>

        <table class="meta">
          <tr>
            <td class="label">Control Number</td>
            <td><strong>{{ $issuance->control_number }}</strong></td>
          </tr>
          <tr>
            <td class="label">Type</td>
            <td><span class="badge">{{ $issuance->type_label }}</span></td>
          </tr>
          <tr>
            <td class="label">Title / Subject</td>
            <td>{{ $issuance->title }}</td>
          </tr>
          <tr>
            <td class="label">Released By</td>
            <td>{{ $issuance->creator?->name ?? 'OCD' }}</td>
          </tr>
          <tr>
            <td class="label">Date Released</td>
            <td>{{ $issuance->released_at?->format('F j, Y g:i A') }}</td>
          </tr>
        </table>

        <p style="font-size:13px;color:#64748b;">Log in to CRCMIS to read the full issuance and acknowledge receipt. You may also verify its authenticity using the QR code.</p>
      </div>

      <div class="actions">
        <a href="{{ $viewUrl }}" class="btn">View & Acknowledge</a>
        <a href="{{ $verifyUrl }}" class="btn btn-verify">Verify Authenticity</a>
      </div>

      <div class="footer">
        This is an official communication from PSHS-CRC via CRCMIS. Do not reply to this email.
      </div>
    </div>
  </div>
</body>
</html>
