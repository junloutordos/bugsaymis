<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Issuance Verification — Atlas</title>
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
  .status.warning { background:#eff6ff; border:1px solid #bfdbfe; }
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
  .sig-block { background:#f8fafc; border-radius:8px; padding:14px; text-align:center; margin-bottom:16px; }
  .sig-img   { max-height:55px; max-width:180px; margin:4px 0; }
  .sig-name  { font-weight:700; font-size:13px; border-top:1px solid #cbd5e1; padding-top:6px; margin-top:4px; display:inline-block; }
  .hash-box  { background:#f1f5f9; border-radius:6px; padding:10px 14px; font-family:monospace; font-size:10px; color:#64748b; word-break:break-all; margin-bottom:14px; }
  .footer    { border-top:1px solid #f1f5f9; padding:14px 24px; font-size:11px; color:#94a3b8; text-align:center; }
  .badge { display:inline-block; padding:2px 10px; border-radius:999px; font-size:11px; font-weight:700; background:#dbeafe; color:#1e40af; }
</style>
</head>
<body>
<div class="card">
  <div class="header">
    <div class="header-school">Philippine Science High School – Caraga Region Campus</div>
    <div class="header-title">Issuance Verification</div>
  </div>

  <div class="body">
    @if(! $valid)
    <div class="status invalid">
      <div class="status-icon">❌</div>
      <div>
        <div class="status-title" style="color:#dc2626;">Document Not Found</div>
        <div class="status-sub">This QR code does not match any released issuance in the Atlas system.</div>
      </div>
    </div>

    @elseif($tampered && $reason === 'signature')
    <div class="status tampered">
      <div class="status-icon">⛔</div>
      <div>
        <div class="status-title" style="color:#dc2626;">Signature Invalid</div>
        <div class="status-sub">The digital signature record for this document failed verification. This document cannot be trusted.</div>
      </div>
    </div>

    @elseif($tampered && $reason === 'content')
    <div class="status tampered">
      <div class="status-icon">⚠️</div>
      <div>
        <div class="status-title" style="color:#ea580c;">Document Tampered — Content Changed</div>
        <div class="status-sub">This document's content no longer matches the version that was digitally signed. Do not trust this document.</div>
      </div>
    </div>

    @elseif($tampered)
    <div class="status tampered">
      <div class="status-icon">⚠️</div>
      <div>
        <div class="status-title" style="color:#ea580c;">Document May Be Tampered</div>
        <div class="status-sub">The content hash does not match the original. This document may have been altered after signing.</div>
      </div>
    </div>

    @elseif($reason === 'no_signature')
    <div class="status warning">
      <div class="status-icon">ℹ️</div>
      <div>
        <div class="status-title" style="color:#1d4ed8;">Valid Record — Not Digitally Signed</div>
        <div class="status-sub">This issuance was found in the system, but has no digital signature on file. Its integrity cannot be cryptographically verified.</div>
      </div>
    </div>

    @else
    <div class="status valid">
      <div class="status-icon">✅</div>
      <div>
        <div class="status-title" style="color:#16a34a;">Authentic & Unmodified</div>
        <div class="status-sub">
          @if($reason === 'legacy_ok')
            This issuance has been verified as authentic via content hash (legacy check — no digital signature on file).
          @elseif($sig?->signature_type === 'kms')
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
      <div class="doc-hint">System Copy — compare this with the printed document in your hand. If the text, names, dates, or amounts differ, the printed copy may have been altered.</div>
      <iframe src="{{ $documentUrl }}" title="Original document"></iframe>
      <a href="{{ $documentUrl }}" target="_blank" class="doc-btn">View / Download Original Document</a>
    </div>

    <div class="meta">
      <div class="meta-row">
        <div class="meta-label">Control No.</div>
        <div class="meta-value"><strong>{{ $issuance->control_number }}</strong></div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Type</div>
        <div class="meta-value"><span class="badge">{{ $issuance->type_label }}</span></div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Title</div>
        <div class="meta-value">{{ $issuance->title }}</div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Issued By</div>
        <div class="meta-value">{{ $issuance->creator?->name ?? '—' }}<br><small style="color:#64748b;">{{ $issuance->creator?->position ?? '' }}</small></div>
      </div>
      <div class="meta-row">
        <div class="meta-label">Date Released</div>
        <div class="meta-value">{{ $issuance->released_at?->format('F d, Y \a\t g:i A') ?? '—' }}</div>
      </div>
      @if($sig)
      <div class="meta-row">
        <div class="meta-label">Digitally Signed</div>
        <div class="meta-value">{{ $sig->signed_at?->format('F d, Y \a\t g:i A') }}</div>
      </div>
      @endif
    </div>

    @if($sig)
    <div class="sig-block">
      <div style="font-size:11px; color:#64748b; margin-bottom:4px;">Signed by:</div>
      @if($sigUri)
      <img src="{{ $sigUri }}" class="sig-img" alt="Digital Signature" />
      @endif
      <div class="sig-name">{{ $sig->signer?->name ?? '—' }}</div>
      <div style="font-size:11px; color:#64748b;">{{ $sig->signer?->position ?? '' }}</div>
    </div>
    @endif

    @if($sig?->document_hash)
    <div style="font-size:11px; color:#64748b; margin-bottom:4px;">
      Signed Document Hash (SHA-256{{ $sig->signature_type === 'kms' ? ', AWS KMS RSA-2048' : ', HMAC-SHA256' }}):
    </div>
    <div class="hash-box">{{ $sig->document_hash }}</div>
    @elseif($issuance->content_hash)
    <div style="font-size:11px; color:#64748b; margin-bottom:4px;">SHA-256 Content Hash:</div>
    <div class="hash-box">{{ $issuance->content_hash }}</div>
    @endif
    @endif
  </div>

  <div class="footer">
    Verified via Atlas · Philippine Science High School – Caraga Region Campus · mis.crc.pshs.edu.ph
  </div>
</div>
</body>
</html>
