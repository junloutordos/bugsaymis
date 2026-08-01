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
.identity { width:100%; border-collapse:collapse; margin:8px 0 12px; font-size:8pt; }
.identity td { padding:2px 5px; }
.nothing-follows td { text-align:center; font-style:italic; color:#475569; }
.sr-note { margin-top:7px; font-size:7pt; color:#64748b; }
</style>
</head>
<body>
<div class="meta">Control No.: <strong>{{ $issued->control_number }}</strong><br>Date Issued: {{ $issued->issued_at->format('F j, Y') }}</div>
<div class="title">{{ $request->type->name }}</div>

@if($request->type->template_key === 'service_record')
<table class="identity">
<tr><td width="14%"><strong>NAME:</strong></td><td width="38%">{{ $request->document_payload['employee_name'] ?? '—' }}</td><td width="14%"><strong>EMPLOYEE NO.:</strong></td><td>{{ $request->document_payload['employee_no'] ?? '—' }}</td></tr>
<tr><td><strong>BIRTH:</strong></td><td>{{ !empty($request->document_payload['birth_date']) ? \Carbon\Carbon::parse($request->document_payload['birth_date'])->format('F j, Y') : '—' }}</td><td><strong>PLACE:</strong></td><td>{{ $request->document_payload['birth_place'] ?? '—' }}</td></tr>
</table>
<p style="font-size:8pt;margin:5px 0 8px;">This is to certify that the employee named above actually rendered services as shown below, each period being supported by appointment and other official papers on file.</p>
<table class="service">
<thead>
<tr><th colspan="2">Service<br>Inclusive Dates</th><th colspan="3">Record of Appointment</th><th colspan="2">Office / Entity / Division</th><th>Leave of Absence<br>Without Pay</th><th colspan="2">Separation</th></tr>
<tr><th>From</th><th>To</th><th>Designation</th><th>Status / Nature</th><th>Salary</th><th>Station / Place</th><th>Branch</th><th>Inclusive Dates</th><th>Date</th><th>Cause</th></tr>
</thead>
<tbody>
@forelse($request->document_payload['service_rows'] ?? [] as $row)
<tr>
<td>{{ !empty($row['from']) ? \Carbon\Carbon::parse($row['from'])->format('m/d/Y') : '—' }}</td>
<td>{{ !empty($row['to']) ? \Carbon\Carbon::parse($row['to'])->format('m/d/Y') : 'Present' }}</td>
<td>{{ $row['position'] ?? '—' }}</td>
<td>{{ collect([$row['employment_status'] ?? null, $row['appointment_nature'] ?? null])->filter()->map(fn($value) => \Illuminate\Support\Str::headline($value))->join(' / ') ?: '—' }}</td>
<td>@if(isset($row['salary_amount']))PHP {{ number_format((float) $row['salary_amount'], 2) }}<br>{{ \Illuminate\Support\Str::headline($row['salary_basis'] ?? '') }}@else—@endif</td>
<td>{{ $row['station_place'] ?? $row['agency'] ?? '—' }}</td>
<td>{{ $row['branch_division'] ?? '—' }}</td>
<td>@forelse($row['lwop_periods'] ?? [] as $lwop){{ !empty($lwop['from']) ? \Carbon\Carbon::parse($lwop['from'])->format('m/d/Y') : '—' }}–{{ !empty($lwop['to']) ? \Carbon\Carbon::parse($lwop['to'])->format('m/d/Y') : '—' }}{{ !$loop->last ? '; ' : '' }}@empty None @endforelse</td>
<td>{{ !empty($row['separation_date']) ? \Carbon\Carbon::parse($row['separation_date'])->format('m/d/Y') : '—' }}</td>
<td>{{ $row['separation_cause'] ?? '—' }}</td>
</tr>
@empty
<tr><td colspan="10" style="text-align:center;">No verified service entries supplied.</td></tr>
@endforelse
@if(!empty($request->document_payload['service_rows']))<tr class="nothing-follows"><td colspan="10">*** NOTHING FOLLOWS ***</td></tr>@endif
</tbody>
</table>
<p class="sr-note">Only entries verified by the Human Resource Office are included. Job Order and Contract of Service engagements are not credited as government service.</p>
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
