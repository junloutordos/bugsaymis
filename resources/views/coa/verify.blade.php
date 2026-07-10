<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificate of Appearance Verification — Atlas</title>
<style>
  *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
  body { font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif; background:#f1f5f9; color:#1e293b; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
  .card { background:#fff; border-radius:16px; box-shadow:0 8px 32px rgba(0,0,0,.10); width:100%; max-width:540px; overflow:hidden; }
  .header { padding:20px 24px 18px; background:linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%); color:#fff; }
  .header-school { font-size:11px; opacity:.7; letter-spacing:1px; text-transform:uppercase; }
  .header-title  { font-size:18px; font-weight:700; margin-top:2px; }
  .body  { padding:24px; }
  .status { display:flex; align-items:center; gap:12px; padding:16px; border-radius:10px; margin-bottom:20px; }
  .status.valid   { background:#f0fdf4; border:1px solid #86efac; }
  .status.invalid { background:#fff1f2; border:1px solid #fca5a5; }
  .status.tampered { background:#fff7ed; border:1px solid #fed7aa; }
  .doc-section { margin-bottom:16px; }
  .doc-section iframe { width:100%; height:360px; border:1px solid #e2e8f0; border-radius:8px; display:block; }
  .doc-btn { display:block; text-align:center; margin-top:8px; background:#1447c0; color:#fff; padding:10px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none; }
  .doc-hint { font-size:11px; color:#64748b; margin-bottom:6px; }
  .status-icon { font-size:28px; }
  .status-title { font-size:15px; font-weight:700; }
  .status-sub   { font-size:12px; margin-top:2px; color:#64748b; }
  .meta { border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; margin-bottom:16px; }
  .meta-row { display:flex; border-bottom:1px solid #f1f5f9; font-size:13px; }
  .meta-row:last-child { border-bottom:none; }
  .meta-label { padding:9px 14px; color:#64748b; font-weight:600; width:42%; background:#f8fafc; }
  .meta-value { padding:9px 14px; flex:1; }
  .hash-box  { background:#f1f5f9; border-radius:6px; padding:10px 14px; font-family:monospace; font-size:10px; color:#64748b; word-break:break-all; margin-bottom:14px; }
  .footer    { border-top:1px solid #f1f5f9; padding:14px 24px; font-size:11px; color:#94a3b8; text-align:center; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <div class="header-school">Philippine Science High School – Caraga Region Campus</div>
    <div class="header-title">Certificate of Appearance Verification</div>
  </div>

  <div class="body">
    @if(! $valid)
    <div class="status invalid">
      <div class="status-icon">❌</div>
      <div>
        <div class="status-title" style="color:#dc2626;">Certificate Not Found</div>
        <div class="status-sub">This QR code does not match any issued Certificate of Appearance in the Atlas system.</div>
      </div>
    </div>

    @elseif($tampered && $reason === 'signature')
    <div class="status tampered">
      <div class="status-icon">⛔</div>
      <div>
        <div class="status-title" style="color:#dc2626;">Signature Invalid</div>
        <div class="status-sub">The digital signature record for this certificate failed verification. This certificate cannot be trusted.</div>
      </div>
    </div>

    @elseif($tampered)
    <div class="status tampered">
      <div class="status-icon">⚠️</div>
      <div>
        <div class="status-title" style="color:#ea580c;">Certificate Tampered — Content Changed</div>
        <div class="status-sub">This certificate's details no longer match the version that was digitally signed. Do not trust this certificate.</div>
      </div>
    </div>

    @else
    <div class="status valid">
      <div class="status-icon">✅</div>
      <div>
        <div class="status-title" style="color:#16a34a;">Authentic & Unmodified</div>
        <div class="status-sub">
          @if($sig?->signature_type === 'kms')
            Cryptographically verified — digitally signed with AWS KMS (RSA-2048).
          @else
            Cryptographically verified — content hash matches the signed original.
          @endif
        </div>
      </div>
    </div>
    @endif

    @if($valid)
    <div class="doc-section">
      <div class="doc-hint">System Copy — compare this with the printed or emailed certificate. If the name, dates, or purpose differ, that copy may have been altered.</div>
      <iframe src="{{ $documentUrl }}" title="Original certificate"></iframe>
      <a href="{{ $documentUrl }}" target="_blank" class="doc-btn">View / Download Original Certificate</a>
    </div>

    <div class="meta">
      <div class="meta-row">
        <div class="meta-label">Control No.</div>
        <div class="meta-value"><strong>{{ $certificate->control_number }}</strong></div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Name</div>
        <div class="meta-value">{{ $certificate->visitor_name }}</div>
      </div>
      @if($certificate->organization)
      <div class="meta-row">
        <div class="meta-label">Organization</div>
        <div class="meta-value">{{ $certificate->organization }}</div>
      </div>
      @endif
      <div class="meta-row">
        <div class="meta-label">Date of Appearance</div>
        <div class="meta-value">
          {{ $certificate->visit->date_from?->format('F d, Y') }}@if($certificate->visit->date_to && ! $certificate->visit->date_to->equalTo($certificate->visit->date_from)) – {{ $certificate->visit->date_to->format('F d, Y') }}@endif
        </div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Purpose</div>
        <div class="meta-value">{{ $certificate->visit->purpose }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Date Issued</div>
        <div class="meta-value">{{ $certificate->visit->issued_at?->format('F d, Y \a\t g:i A') ?? '—' }}</div>
      </div>
      @if($sig)
      <div class="meta-row">
        <div class="meta-label">Issued By</div>
        <div class="meta-value">{{ $sig->signer?->name ?? '—' }}<br><small style="color:#64748b;">{{ $sig->signer?->position ?? '' }}</small></div>
      </div>
      @endif
    </div>

    @if($sig?->document_hash)
    <div style="font-size:11px; color:#64748b; margin-bottom:4px;">
      Signed Document Hash (SHA-256{{ $sig->signature_type === 'kms' ? ', AWS KMS RSA-2048' : ', HMAC-SHA256' }}):
    </div>
    <div class="hash-box">{{ $sig->document_hash }}</div>
    @endif
    @endif
  </div>

  <div class="footer">
    Verified via Atlas · Philippine Science High School – Caraga Region Campus · mis.crc.pshs.edu.ph
  </div>
</div>
</body>
</html>
