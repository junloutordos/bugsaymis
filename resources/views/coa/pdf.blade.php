<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial, sans-serif; font-size:11pt; color:#1e293b; line-height:1.7; }

.control-line { text-align:right; font-size:9.5pt; color:#1447c0; margin:10px 0 2px; }
.date-line    { text-align:right; font-size:9pt; color:#475569; }

.cert-title { text-align:center; font-size:17pt; font-weight:bold; color:#060e50;
              text-transform:uppercase; letter-spacing:3px; margin:34px 0 30px; }

.cert-body { text-align:justify; margin:0 0 14px; text-indent:36pt; }
.cert-body strong { font-weight:bold; }

.sig-block { margin-top:46px; }
.sig-block .signed-by { font-size:8.5pt; color:#64748b; margin-bottom:4px; }
.sig-img   { max-height:55px; max-width:170px; object-fit:contain; }
.sig-name  { font-weight:bold; font-size:11pt; border-top:1px solid #334155; padding-top:4px; margin-top:4px; display:inline-block; min-width:220px; }
.sig-pos   { font-size:8.5pt; color:#475569; }
</style>
</head>
<body>

{{-- QR code is stamped programmatically via mPDF Image() API in CertificateOfAppearanceService --}}
{{-- Letterhead provided by mPDF header image (report_header.jpeg) --}}

@php
    $dateFrom = $visit->date_from?->format('F j, Y');
    $dateTo   = $visit->date_to && ! $visit->date_to->equalTo($visit->date_from)
        ? $visit->date_to->format('F j, Y') : null;
    $appearedOn = $dateTo ? "{$dateFrom} to {$dateTo}" : $dateFrom;
    $sig    = $cert->signature;
    $issuer = $sig?->signer;
@endphp

<div class="control-line">Control No.: <strong>{{ $cert->control_number }}</strong></div>
<div class="date-line">Date Issued: {{ ($visit->issued_at ?? now())->format('F j, Y') }}</div>

<div class="cert-title">Certificate of Appearance</div>

<p class="cert-body">
    This is to certify that <strong>{{ mb_strtoupper($cert->visitor_name) }}</strong>@if($cert->position), {{ $cert->position }}@endif
    @if($cert->organization) of <strong>{{ $cert->organization }}</strong>@endif
    personally appeared at the <strong>Philippine Science High School &ndash; Caraga Region Campus</strong>,
    Ampayon, Butuan City, Agusan del Norte on <strong>{{ $appearedOn }}</strong>
    @if($visit->office_visited) at the {{ $visit->office_visited }}@endif
    for the purpose of <strong>{{ $visit->purpose }}</strong>.
</p>

<p class="cert-body">
    This certification is issued upon request of the above-named person for whatever
    legal purpose it may serve.
</p>

<div class="sig-block">
    <div class="signed-by">Issued and digitally signed by:</div>
    <div style="height:14px;"></div>
    <div class="sig-name">{{ $issuer?->name ?? 'PSHS-CRC' }}</div>
    <div class="sig-pos">{{ $issuer?->position ?? 'Philippine Science High School – Caraga Region Campus' }}</div>
    @if($sig)
    <div style="font-size:7.5pt; color:#64748b; margin-top:3px;">
        Digitally signed (PIN-verified): {{ $sig->signed_at?->format('F d, Y \a\t h:i A') }}
    </div>
    @endif
</div>

</body>
</html>
