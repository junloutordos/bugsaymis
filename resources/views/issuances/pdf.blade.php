<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial, sans-serif; font-size:10pt; color:#1e293b; line-height:1.6; }

/* ── Issuance type header ── */
.issuance-header { text-align:center; margin:16px 0 12px; }
.issuance-type   { font-size:13pt; font-weight:bold; color:#060e50; text-transform:uppercase; letter-spacing:1px; }
.control-no      { font-size:10pt; color:#1447c0; margin-top:2px; }
.subject-line    { font-size:10pt; margin-top:6px; }

/* ── Body ── */
.body-content { margin:14px 0; text-align:justify; }
.body-content p { margin-bottom:8px; }

/* Rich text content styles */
.body-content h1 { font-size:16pt; font-weight:bold; margin:8px 0 6px; }
.body-content h2 { font-size:13pt; font-weight:bold; margin:7px 0 5px; }
.body-content h3 { font-size:11pt; font-weight:bold; margin:6px 0 4px; }
.body-content ul { padding-left:18px; margin:4px 0; list-style-type:disc; }
.body-content ol { padding-left:18px; margin:4px 0; list-style-type:decimal; }
.body-content li { margin:2px 0; }
.body-content strong { font-weight:bold; }
.body-content em { font-style:italic; }
.body-content u { text-decoration:underline; }
.body-content s { text-decoration:line-through; }
.body-content blockquote { border-left:3px solid #cbd5e1; padding-left:10px; color:#475569; margin:5px 0; font-style:italic; }
.body-content hr { border:none; border-top:1px solid #e2e8f0; margin:8px 0; }
.body-content table { width:100%; border-collapse:collapse; margin:8px 0; font-size:9pt; }
.body-content th, .body-content td { border:1px solid #cbd5e1; padding:5px 8px; vertical-align:top; }
.body-content th { background:#f1f5f9; font-weight:bold; }

/* ── Signature block ── */
.sig-block { margin-top:30px; }
.sig-block .signed-by { font-size:8.5pt; color:#64748b; margin-bottom:4px; }
.sig-img   { max-height:55px; max-width:170px; object-fit:contain; }
.sig-name  { font-weight:bold; font-size:10pt; border-top:1px solid #334155; padding-top:4px; margin-top:4px; display:inline-block; min-width:200px; }
.sig-pos   { font-size:8.5pt; color:#475569; }


</style>
</head>
<body>

{{-- QR code is stamped programmatically via mPDF Image() API in IssuanceService::stampQrNative() --}}

{{-- Letterhead provided by mPDF header image (report_header.jpeg) --}}

{{-- Issuance type header --}}
<div class="issuance-header">
  <div class="issuance-type">{{ $issuance->isSupplement() ? $issuance->document_kind_label : $issuance->type_label }}</div>
  <div class="control-no">
    {{ $issuance->isSupplement() ? $issuance->display_number : 'No. '.$issuance->series_no.', S. '.$issuance->year }}
  </div>
  @if($issuance->isSupplement() && $issuance->parentIssuance)
  <div class="subject-line">Supplement to: {{ $issuance->parentIssuance->title }}</div>
  @endif
</div>

{{-- Body content (editor mode only — scan mode uses stampQrOnScan) --}}
@if($issuance->content)
<div class="body-content">
  {!! $issuance->content !!}
</div>
@endif

{{-- Signature block --}}
<div class="sig-block" style="margin-top:30px;">
  <div class="signed-by">Signed by:</div>
  @if($sigUri)
  <img src="{{ $sigUri }}" class="sig-img" alt="Digital Signature" />
  @else
  <div style="height:50px;"></div>
  @endif
  <div class="sig-name">{{ $ocdUser?->name ?? 'Campus Director' }}</div>
  <div class="sig-pos">{{ $ocdUser?->position ?? 'Campus Director, PSHS-CRC' }}</div>
  @if($sig)
  <div style="font-size:7.5pt; color:#64748b; margin-top:3px;">
    Digitally signed: {{ $sig->signed_at?->format('F d, Y \a\t h:i A') }}
  </div>
  @endif
</div>


</body>
</html>
