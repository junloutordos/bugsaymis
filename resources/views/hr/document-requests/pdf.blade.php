<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing:border-box; }
body { font-family:Arial,sans-serif; color:#1e293b; font-size:11pt; line-height:1.65; }
.meta { text-align:right; font-size:8.5pt; color:#475569; }
.title { text-align:center; font-weight:bold; font-size:16pt; color:#060e50; text-transform:uppercase; letter-spacing:1.5px; margin:34px 0 28px; }
.body { text-align:justify; text-indent:34pt; margin-bottom:16px; }
.service { width:100%; border-collapse:collapse; margin:12px 0; font-size:8pt; }
.service th,.service td { border:1px solid #64748b; padding:5px; vertical-align:top; }
.service th { background:#e2e8f0; text-transform:uppercase; font-size:7pt; }
.purpose { margin-top:20px; font-size:9pt; color:#475569; }
.sig { margin-top:38px; }
.sig img { max-width:170px; max-height:55px; }
.sig-name { font-size:11pt; font-weight:bold; margin-top:3px; }
.sig-pos { font-size:8.5pt; color:#475569; }
.signed-at { font-size:7.5pt; color:#64748b; }
</style>
</head>
<body>
<div class="meta">Control No.: <strong>{{ $issued->control_number }}</strong><br>Date Issued: {{ $issued->issued_at->format('F j, Y') }}</div>
<div class="title">{{ $request->type->name }}</div>

@if($request->type->template_key === 'service_record')
<p class="body">This is to certify that the following is the service record of <strong>{{ $request->document_payload['employee_name'] }}</strong> based on the records reviewed by the Human Resource Office:</p>
<table class="service">
<thead><tr><th>From</th><th>To</th><th>Designation</th><th>Station / Agency</th><th>Appointment</th><th>Gov't Service</th></tr></thead>
<tbody>
@forelse($request->document_payload['service_rows'] ?? [] as $row)
<tr>
<td>{{ $row['from'] ?? '—' }}</td><td>{{ $row['to'] ?? '—' }}</td>
<td>{{ $row['position'] ?? '—' }}</td><td>{{ $row['agency'] ?? '—' }}</td>
<td>{{ $row['appointment_status'] ?? '—' }}</td><td>{{ $row['government_service'] ?? '—' }}</td>
</tr>
@empty
<tr><td colspan="6" style="text-align:center;">No service entries supplied.</td></tr>
@endforelse
</tbody>
</table>
@else
<p class="body">{!! nl2br(e($request->document_payload['body'] ?? '')) !!}</p>
@endif

<p class="body">{{ $request->document_payload['certification_clause'] ?? '' }}</p>
<p class="purpose"><strong>Purpose:</strong> {{ $request->purpose }}</p>

<div class="sig">
<div style="font-size:8pt;color:#64748b;">Digitally signed by:</div>
@if($signatureUri)<img src="{{ $signatureUri }}" alt="Digital Signature">@else<div style="height:48px;"></div>@endif
<div class="sig-name">{{ $signerName }}</div>
<div class="sig-pos">{{ $signer?->position ?? 'Authorized Official, PSHS-CRC' }}</div>
<div class="signed-at">PIN-verified digital signature: {{ $signature?->signed_at?->format('F j, Y \a\t h:i A') }}</div>
</div>
</body>
</html>
