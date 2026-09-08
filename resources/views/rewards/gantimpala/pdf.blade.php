<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing:border-box; }
body { font-family:Arial,sans-serif; color:#111827; font-size:9.5pt; line-height:1.45; }
.agency { text-align:center; font-size:9pt; line-height:1.3; margin-bottom:2px; }
.agency .strong { font-weight:bold; }
.program { text-align:center; font-size:9.5pt; font-weight:bold; margin-top:10px; text-transform:uppercase; }
.title { text-align:center; font-size:13pt; font-weight:bold; margin:2px 0 14px; text-transform:uppercase; letter-spacing:0.5px; }
.ref { text-align:right; font-size:8pt; color:#475569; margin-bottom:6px; }
table.section { width:100%; border-collapse:collapse; margin-bottom:12px; }
table.section th { background:#e2e8f0; text-align:left; font-size:9.5pt; padding:5px 7px; border:1px solid #64748b; text-transform:uppercase; letter-spacing:0.3px; }
table.section td { border:1px solid #64748b; padding:5px 7px; vertical-align:top; }
table.section td.label { width:32%; font-weight:bold; background:#f8fafc; }
.narrative { min-height:46px; white-space:pre-wrap; }
.cert-note { margin:14px 0 22px; font-size:9pt; text-align:justify; }
table.sig { width:100%; border-collapse:collapse; margin-top:6px; }
table.sig td { width:50%; text-align:center; vertical-align:bottom; padding-top:36px; }
.sig-line { border-top:1px solid #111827; padding-top:3px; font-size:9pt; }
.sig-caption { font-size:7.5pt; color:#475569; }
.sig-img { max-width:150px; max-height:50px; display:block; margin:0 auto 4px; }
.status-badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:7.5pt; font-weight:bold; text-transform:uppercase; }
.status-approved { background:#d1fae5; color:#065f46; }
.status-endorsed { background:#dbeafe; color:#1e40af; }
.status-rejected { background:#fee2e2; color:#991b1b; }
.status-pending, .status-under_review { background:#fef3c7; color:#92400e; }
.status-archived { background:#e5e7eb; color:#374151; }
.footer-note { margin-top:24px; font-size:7.5pt; color:#64748b; text-align:center; }
</style>
</head>
<body>

<div class="agency">
    <div class="strong">Republic of the Philippines</div>
    <div>Department of Science and Technology</div>
    <div class="strong">PHILIPPINE SCIENCE HIGH SCHOOL – CARAGA REGION CAMPUS IN BUTUAN CITY</div>
    <div>Ampayon, Butuan City</div>
</div>

<div class="ref">Reference No.: <strong>{{ $nomination->reference_no }}</strong><br>Date Generated: {{ now()->format('F j, Y') }}</div>

<div class="program">Program on Awards and Incentives for Service Excellence (PRAISE)</div>
<div class="title">Nomination Form 4 — Gantimpala Agad Award</div>

<table class="section">
<tr><th colspan="2">Employee / Unit Commended</th></tr>
<tr><td class="label">Name (Person or Unit)</td><td>{{ $nomination->nominee_name }}</td></tr>
<tr><td class="label">Title</td><td>{{ $nomination->nominee_title ?? '—' }}</td></tr>
<tr><td class="label">Department</td><td>{{ $nomination->nominee_department ?? '—' }}</td></tr>
<tr><td class="label">Address</td><td>{{ $nomination->nominee_address ?? '—' }}</td></tr>
<tr><td class="label">Contact Number</td><td>{{ $nomination->nominee_contact_number ?? '—' }}</td></tr>
<tr><td class="label">Date Submitted</td><td>{{ optional($nomination->date_submitted)->format('F j, Y') ?? '—' }}</td></tr>
</table>

<table class="section">
<tr><th colspan="2">Background of Commendation — Person/Entity Providing Commendation</th></tr>
<tr><td class="label">Name</td><td>{{ $nomination->nominator_name }}</td></tr>
<tr><td class="label">Address</td><td>{{ $nomination->nominator_address ?? '—' }}</td></tr>
<tr><td class="label">Contact Number</td><td>{{ $nomination->nominator_contact_number ?? '—' }}</td></tr>
<tr><td class="label">E-mail Address</td><td>{{ $nomination->nominator_email ?? '—' }}</td></tr>
</table>

<table class="section">
<tr><th colspan="2">Details of Commendable Action</th></tr>
<tr><td class="label">Activity Conducted / Service Provided</td><td>{{ $nomination->activity_conducted }}</td></tr>
<tr><td class="label">Date</td><td>{{ optional($nomination->activity_date)->format('F j, Y') ?? '—' }}</td></tr>
<tr><td class="label">Venue/Location</td><td>{{ $nomination->venue_location ?? '—' }}</td></tr>
<tr><td class="label">Other Information</td><td class="narrative">{{ $nomination->other_information ?? '—' }}</td></tr>
</table>

<p class="cert-note">I certify to the correctness of the information provided herewith.</p>

<table class="sig">
<tr>
    <td>
        @if($nominatorSignatureUri)
            <img class="sig-img" src="{{ $nominatorSignatureUri }}" alt="Nominator Signature">
        @endif
        <div class="sig-line">{{ $nomination->nominator_name }}</div>
        <div class="sig-caption">Nominator (Printed Name and Signature)</div>
    </td>
    <td>
        @if($supervisorSignatureUri)
            <img class="sig-img" src="{{ $supervisorSignatureUri }}" alt="Supervisor Signature">
        @endif
        <div class="sig-line">{{ $nomination->supervisor_name ?? '________________________' }}</div>
        <div class="sig-caption">Endorsed by Supervisor (Printed Name and Signature)</div>
    </td>
</tr>
</table>

<p style="margin-top:16px; font-size:9pt;">
    Date: {{ optional($nomination->endorsed_at)->format('F j, Y') ?? '_____________' }}
    &nbsp; &nbsp; &nbsp;
    Status: <span class="status-badge status-{{ $nomination->status }}">{{ str_replace('_', ' ', $nomination->status) }}</span>
</p>

<div class="footer-note">Generated via Atlas (BugSayMis) — PSHS-CRC Campus Management Information System. Scan the QR code to verify authenticity.</div>

</body>
</html>
