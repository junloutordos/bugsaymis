<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document Verification — {{ $jobRequest->itjr_no }}</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif; font-size: 14px; background: #f1f5f9; color: #1e293b; min-height: 100vh; }

    .wrapper { max-width: 480px; margin: 0 auto; padding: 0 0 60px; }

    /* Header */
    .site-header { background: #fff; border-bottom: 1px solid #e2e8f0; padding: 14px 20px; display: flex; align-items: center; gap: 12px; }
    .site-header .shield { width: 36px; height: 36px; background: #eff6ff; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .site-header .shield svg { width: 20px; height: 20px; color: #2563eb; }
    .site-header .school { font-size: 11px; color: #64748b; line-height: 1.3; }
    .site-header .school strong { display: block; font-size: 13px; color: #1e293b; font-weight: 700; }

    /* Status banner */
    .banner { margin: 16px 16px 0; border-radius: 12px; padding: 16px 18px; display: flex; align-items: flex-start; gap: 12px; }
    .banner.all-valid { background: #16a34a; color: #fff; }
    .banner.partial   { background: #d97706; color: #fff; }
    .banner.none      { background: #dc2626; color: #fff; }
    .banner .icon { width: 36px; height: 36px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 1px; }
    .banner .icon svg { width: 20px; height: 20px; }
    .banner h2 { font-size: 16px; font-weight: 700; line-height: 1.3; }
    .banner p  { font-size: 12px; opacity: 0.9; margin-top: 2px; line-height: 1.4; }

    /* Cards */
    .card { background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; margin: 12px 16px 0; overflow: hidden; }

    /* Doc title card */
    .doc-title { padding: 16px 18px; border-bottom: 1px solid #f1f5f9; }
    .doc-title .label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 4px; }
    .doc-title .itjr  { font-size: 18px; font-weight: 700; color: #1e293b; }
    .doc-title .meta  { font-size: 12px; color: #64748b; margin-top: 4px; }

    /* Sig card */
    .sig-card { }
    .sig-card + .sig-card { border-top: 1px solid #f1f5f9; }

    .sig-section { padding: 14px 18px 0; }
    .sig-section .section-label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 10px; }

    .sig-body { display: flex; align-items: flex-start; gap: 14px; }
    .sig-img-wrap { width: 72px; height: 52px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc; flex-shrink: 0; display: flex; align-items: center; justify-content: center; overflow: hidden; }
    .sig-img-wrap img { max-width: 100%; max-height: 100%; object-fit: contain; }
    .sig-img-wrap .no-sig { font-size: 10px; color: #cbd5e1; text-align: center; padding: 4px; }
    .sig-info .name     { font-size: 15px; font-weight: 700; color: #1e293b; }
    .sig-info .position { font-size: 12px; color: #64748b; margin-top: 2px; }
    .sig-info .badge    { font-size: 11px; color: #94a3b8; margin-top: 2px; }

    .sig-badge { margin: 10px 18px 0; }
    .badge-valid   { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #dcfce7; color: #15803d; }
    .badge-invalid { display: inline-flex; align-items: center; gap: 5px; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; background: #fee2e2; color: #b91c1c; }

    .sig-details { padding: 12px 18px 14px; }
    .detail-row { display: flex; padding: 7px 0; border-bottom: 1px solid #f8fafc; }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { font-size: 10px; font-weight: 700; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.06em; width: 110px; flex-shrink: 0; padding-top: 1px; }
    .detail-value { font-size: 13px; color: #1e293b; font-weight: 500; }
    .detail-value.token { font-size: 10px; color: #64748b; font-family: monospace; word-break: break-all; font-weight: 400; }

    .footer-note { text-align: center; font-size: 11px; color: #94a3b8; margin: 20px 16px 0; line-height: 1.6; }
  </style>
</head>
<body>
  <div class="wrapper">

    {{-- Header --}}
    <div class="site-header">
      <div class="shield">
        <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
          <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd" />
        </svg>
      </div>
      <div class="school">
        PHILIPPINE SCIENCE HIGH SCHOOL
        <strong>CRCMIS Document Verification</strong>
      </div>
    </div>

    @php
      $totalCount = $entries->count();
      $validCount = $entries->filter(fn($e) => $e['valid'])->count();
    @endphp

    {{-- Status banner --}}
    @if($totalCount === 0)
      <div class="banner none">
        <div class="icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </div>
        <div>
          <h2>No Signatures Found</h2>
          <p>This document has no recorded digital signatures.</p>
        </div>
      </div>
    @elseif($validCount === $totalCount)
      <div class="banner all-valid">
        <div class="icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.745 3.745 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.745 3.745 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.745 3.745 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
        </div>
        <div>
          <h2>Document Verified</h2>
          <p>All {{ $totalCount }} digital signature(s) are authentic and the document has not been altered.</p>
        </div>
      </div>
    @else
      <div class="banner partial">
        <div class="icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
        </div>
        <div>
          <h2>Partially Verified</h2>
          <p>{{ $validCount }} of {{ $totalCount }} signatures verified. Some signatures may be invalid.</p>
        </div>
      </div>
    @endif

    {{-- Document title card --}}
    <div class="card">
      <div class="doc-title">
        <div class="label">Document</div>
        <div class="itjr">IT Job Request #{{ $jobRequest->itjr_no }}</div>
        <div class="meta">
          {{ $jobRequest->title ?? $jobRequest->description ?? '—' }}<br>
          Requested by: <strong>{{ $jobRequest->user?->name ?? '—' }}</strong>
        </div>
      </div>
    </div>

    {{-- One card per signature --}}
    @foreach($entries as $entry)
    <div class="card">

      {{-- Signed By --}}
      <div class="sig-section">
        <div class="section-label">{{ $entry['stage'] }}</div>
        <div class="sig-body">
          <div class="sig-img-wrap">
            @if($entry['signature_uri'])
              <img src="{{ $entry['signature_uri'] }}" alt="Signature">
            @else
              <span class="no-sig">No image</span>
            @endif
          </div>
          <div class="sig-info">
            <div class="name">{{ $entry['signer'] }}</div>
            <div class="position">{{ $entry['position'] }}</div>
            @if($entry['badge_id'])
              <div class="badge">Badge No. {{ $entry['badge_id'] }}</div>
            @endif
          </div>
        </div>
      </div>

      {{-- Validity badge --}}
      <div class="sig-badge">
        @if($entry['valid'])
          <span class="badge-valid">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
            Signature Valid
          </span>
        @else
          <span class="badge-invalid">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            Signature Invalid
          </span>
        @endif
      </div>

      {{-- Details --}}
      <div class="sig-details">
        <div class="detail-row">
          <span class="detail-label">Date Signed</span>
          <span class="detail-value">{{ $entry['signed_at'] }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Stage</span>
          <span class="detail-value">{{ $entry['stage'] }}</span>
        </div>
        @if(!empty($entry['metadata']['title']))
        <div class="detail-row">
          <span class="detail-label">Title</span>
          <span class="detail-value">{{ $entry['metadata']['title'] }}</span>
        </div>
        @endif
        <div class="detail-row">
          <span class="detail-label">ITJR No</span>
          <span class="detail-value">{{ $jobRequest->itjr_no }}</span>
        </div>
        <div class="detail-row">
          <span class="detail-label">Verification Token</span>
          <span class="detail-value token">{{ $entry['verification_token'] }}</span>
        </div>
      </div>
    </div>
    @endforeach

    @if($entries->isEmpty())
    <div class="card" style="padding:24px;text-align:center;color:#94a3b8;font-size:13px;">
      No digital signatures recorded for this document.
    </div>
    @endif

    <div class="footer-note">
      This verification page is generated by the PSHS-CRC Management Information System.<br>
      Tampering with the document will invalidate all digital signatures.
    </div>

  </div>
</body>
</html>
