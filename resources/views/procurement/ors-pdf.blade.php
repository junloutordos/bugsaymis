<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial, sans-serif; font-size:9pt; color:#000; line-height:1.4; }
h1 { font-size:11pt; font-weight:bold; text-align:center; text-transform:uppercase; }
table { width:100%; border-collapse:collapse; }
td, th { border:1px solid #000; padding:4px 6px; vertical-align:top; }
.label { font-size:7.5pt; color:#333; }
.value { font-size:9pt; font-weight:bold; }
.center { text-align:center; }
.right { text-align:right; }
.sig-line { border-top:1px solid #000; margin-top:20px; padding-top:2px; font-size:8pt; text-align:center; }
.sig-name { font-weight:bold; font-size:9pt; }
.header-agency { font-size:9pt; text-align:center; margin-bottom:4px; }
.form-no { font-size:7.5pt; color:#555; margin-bottom:2px; }
.qr-box { text-align:right; font-size:7pt; color:#555; }
.digital-ref { font-size:7.5pt; color:#1447c0; text-align:right; margin-top:4px; }
.section-label { font-size:7.5pt; font-weight:bold; text-transform:uppercase; background:#f0f0f0; padding:2px 4px; }
.amount-box { font-size:12pt; font-weight:bold; text-align:center; border:2px solid #000; padding:6px; }
</style>
</head>
<body>

{{-- Header --}}
<table style="border:none; margin-bottom:4px;">
  <tr style="border:none;">
    <td style="border:none; width:70%;">
      <div class="header-agency">Republic of the Philippines</div>
      <div class="header-agency"><strong>Philippine Science High School – Caraga Region Campus</strong></div>
      <div class="header-agency">Ampayon, Butuan City, Agusan del Norte</div>
    </td>
    <td style="border:none; width:30%; text-align:right; vertical-align:top;">
      <div class="qr-box">
        <img src="data:image/svg+xml;base64,{{ $qrSvg }}" width="70" height="70" alt="QR" /><br>
        <span class="digital-ref">Digital Record Ref</span>
      </div>
    </td>
  </tr>
</table>

<div style="text-align:right; margin-bottom:2px;" class="form-no">DBM Form B.6 (Revised)</div>
<div style="text-align:center; margin-bottom:6px;"><h1>Obligation Request and Status</h1></div>
<div style="text-align:center; margin-bottom:8px; font-size:8.5pt;">(ORS)</div>

{{-- ORS Header Box --}}
<table style="margin-bottom:0;">
  <tr>
    <td style="width:50%;">
      <div class="label">Entity Name:</div>
      <div class="value">PSHS – Caraga Region Campus</div>
    </td>
    <td style="width:25%;">
      <div class="label">Fund Cluster:</div>
      <div class="value">01</div>
    </td>
    <td style="width:25%;">
      <div class="label">Serial No.:</div>
      <div class="value">{{ $ors->ors_number ?: '—' }}</div>
    </td>
  </tr>
  <tr>
    <td>
      <div class="label">Division/Office:</div>
      <div class="value">{{ optional($ors->division)->division_name ?? '—' }}</div>
    </td>
    <td>
      <div class="label">Date:</div>
      <div class="value">{{ optional($ors->activity_date)->format('m/d/Y') ?? optional($ors->created_at)->format('m/d/Y') ?? '—' }}</div>
    </td>
    <td>
      <div class="label">PR Reference:</div>
      <div class="value">
        {{ optional($ors->procurement)->assigned_pr_number ?: optional($ors->procurement)->pr_no ?: '—' }}
      </div>
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <div class="label">Payee / Supplier:</div>
      <div class="value">{{ $ors->supplier_name ?: '—' }}</div>
    </td>
    <td>
      <div class="label">PO/JO No.:</div>
      <div class="value">{{ $ors->po_number ?: '—' }}</div>
    </td>
  </tr>
</table>

{{-- Account & Activity --}}
<table style="margin-top:0;">
  <tr>
    <td style="width:40%;">
      <div class="label">Account Title:</div>
      <div class="value">{{ $ors->account_title ?: '—' }}</div>
    </td>
    <td style="width:20%;">
      <div class="label">Object Code / UACS:</div>
      <div class="value">{{ $ors->object_code ?: '—' }}</div>
    </td>
    <td style="width:40%;">
      <div class="label">Activity Title:</div>
      <div class="value">{{ $ors->activity_title ?: '—' }}</div>
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <div class="label">Activity Date / Period:</div>
      <div class="value">{{ optional($ors->activity_date)->format('F d, Y') ?? '—' }}</div>
    </td>
    <td>
      <div class="label">Log/eLog Date:</div>
      <div class="value">{{ optional($ors->logged_at)?->format('m/d/Y') ?? '—' }}</div>
    </td>
  </tr>
</table>

{{-- Amount --}}
<table style="margin-top:0;">
  <tr>
    <td style="width:60%;">
      <div class="label">Amount Obligated (in words):</div>
      <div class="value" style="min-height:18px; font-style:italic;">
        {{-- Amount in words would go here if we implement a PHP words converter; omitted for simplicity --}}
        &nbsp;
      </div>
    </td>
    <td style="width:40%;">
      <div class="label">Amount (₱):</div>
      <div class="amount-box">₱ {{ number_format($ors->amount, 2) }}</div>
    </td>
  </tr>
</table>

{{-- Approval Stamps / Signatures --}}
<table style="margin-top:12px; border:none;">
  <tr style="border:none;">
    <td style="border:none; width:33%; text-align:center; padding:0 6px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($ors->divisionChief)->name ?? '________________________' }}</div>
        <div class="label">Division Chief</div>
        @if($ors->division_chief_at)
        <div class="label" style="color:#555;">{{ $ors->division_chief_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="border:none; width:33%; text-align:center; padding:0 6px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($ors->budgetOfficer)->name ?? '________________________' }}</div>
        <div class="label">Budget Officer</div>
        @if($ors->budget_officer_at)
        <div class="label" style="color:#555;">{{ $ors->budget_officer_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="border:none; width:33%; text-align:center; padding:0 6px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($ors->ocd)->name ?? '________________________' }}</div>
        <div class="label">Campus Director / Approving Authority</div>
        @if($ors->ocd_at)
        <div class="label" style="color:#555;">{{ $ors->ocd_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
  </tr>
</table>

{{-- Bookkeeper & Accountant --}}
<table style="margin-top:12px; border:none;">
  <tr style="border:none;">
    <td style="border:none; width:50%; text-align:center; padding:0 8px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($ors->bookkeeper)->name ?? '________________________' }}</div>
        <div class="label">Bookkeeper (Completeness Check)</div>
        @if($ors->bookkeeper_at)
        <div class="label" style="color:#555;">{{ $ors->bookkeeper_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="border:none; width:50%; text-align:center; padding:0 8px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($ors->accountant)->name ?? '________________________' }}</div>
        <div class="label">Accountant (Data Review)</div>
        @if($ors->accountant_at)
        <div class="label" style="color:#555;">{{ $ors->accountant_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
  </tr>
</table>

{{-- Status bar --}}
<table style="margin-top:12px;">
  <tr>
    <td style="width:60%;">
      <div class="label">Remarks / Additional Notes:</div>
      <div class="value" style="min-height:18px;">{{ $ors->budget_officer_remarks ?: '&nbsp;' }}</div>
    </td>
    <td style="width:40%; text-align:center;">
      <div class="label">Status:</div>
      <div class="value" style="font-size:10pt; color:{{ $ors->status === 'completed' ? '#16a34a' : ($ors->status === 'returned_bookkeeper' || $ors->status === 'returned_bo' ? '#dc2626' : '#92400e') }};">
        {{ strtoupper(str_replace('_', ' ', $ors->status)) }}
      </div>
    </td>
  </tr>
</table>

<div style="margin-top:14px; border-top:1px solid #ddd; padding-top:6px; font-size:7.5pt; color:#666; text-align:center;">
  This is a digital record from the PSHS-CRC Management Information System.
  Scan the QR code to verify at {{ config('app.url') }}/ors/{{ $ors->id }}
</div>

</body>
</html>
