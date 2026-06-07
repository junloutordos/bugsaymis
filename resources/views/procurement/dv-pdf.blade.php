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
.amount-box { font-size:12pt; font-weight:bold; text-align:center; border:2px solid #000; padding:6px; }
.phase-header { font-size:8pt; font-weight:bold; text-transform:uppercase; background:#e8e8e8; padding:3px 6px; margin-top:8px; margin-bottom:0; border:1px solid #000; }
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

<div style="text-align:right; margin-bottom:2px;" class="form-no">DBM Form 10-A (Revised)</div>
<div style="text-align:center; margin-bottom:6px;"><h1>Disbursement Voucher</h1></div>

{{-- DV Header --}}
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
      <div class="label">DV No.:</div>
      <div class="value">{{ $dv->dv_number ?: '—' }}</div>
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <div class="label">Mode of Payment:</div>
      <div class="value">{{ $dv->payment_method ? strtoupper($dv->payment_method) : '—' }}</div>
    </td>
    <td>
      <div class="label">Date:</div>
      <div class="value">{{ optional($dv->activity_date)->format('m/d/Y') ?? optional($dv->created_at)->format('m/d/Y') ?? '—' }}</div>
    </td>
  </tr>
  <tr>
    <td colspan="3">
      <div class="label">Payee / Claimant:</div>
      <div class="value">{{ optional($dv->ors)->supplier_name ?? '—' }}</div>
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <div class="label">Activity / Purpose:</div>
      <div class="value">{{ $dv->activity_title ?: optional($dv->ors)->activity_title ?: '—' }}</div>
    </td>
    <td>
      <div class="label">Activity Date:</div>
      <div class="value">{{ optional($dv->activity_date)->format('m/d/Y') ?? '—' }}</div>
    </td>
  </tr>
  <tr>
    <td>
      <div class="label">ORS/BURS No.:</div>
      <div class="value">{{ optional($dv->ors)->ors_number ?? '—' }}</div>
    </td>
    <td>
      <div class="label">PO/JO No.:</div>
      <div class="value">{{ $dv->po_number ?: optional($dv->ors)->po_number ?: '—' }}</div>
    </td>
    <td>
      <div class="label">Amount Obligated (₱):</div>
      <div class="value">{{ number_format(optional($dv->ors)->amount ?? 0, 2) }}</div>
    </td>
  </tr>
</table>

{{-- Amount Breakdown --}}
<table style="margin-top:0;">
  <tr>
    <td style="width:40%;">
      <div class="label">Gross Amount (₱):</div>
      <div class="amount-box">₱ {{ number_format($dv->gross_amount, 2) }}</div>
    </td>
    <td style="width:20%;">
      <div class="label">Tax / Deduction (₱):</div>
      <div class="value" style="text-align:center; font-size:10pt;">₱ {{ number_format($dv->tax_amount ?? 0, 2) }}</div>
    </td>
    <td style="width:40%;">
      <div class="label">Net Amount Payable (₱):</div>
      <div class="amount-box">₱ {{ number_format($dv->net_amount ?? ($dv->gross_amount - ($dv->tax_amount ?? 0)), 2) }}</div>
    </td>
  </tr>
</table>

{{-- Delivery / Inspection (Phase A) --}}
@if($dv->iar_number || $dv->ris_number || $dv->dr_number)
<div class="phase-header">Phase A — Delivery &amp; Inspection</div>
<table>
  <tr>
    <td style="width:33%;">
      <div class="label">IAR No.:</div>
      <div class="value">{{ $dv->iar_number ?: '—' }}</div>
    </td>
    <td style="width:33%;">
      <div class="label">RIS No.:</div>
      <div class="value">{{ $dv->ris_number ?: '—' }}</div>
    </td>
    <td style="width:33%;">
      <div class="label">DR No.:</div>
      <div class="value">{{ $dv->dr_number ?: '—' }}</div>
    </td>
  </tr>
  <tr>
    <td>
      <div class="label">Delivery Accepted:</div>
      <div class="value">{{ optional($dv->delivery_accepted_at)?->format('m/d/Y') ?? '—' }}</div>
    </td>
    <td colspan="2">
      <div class="label">RIS Complete:</div>
      <div class="value">{{ $dv->ris_complete ? 'Yes' : 'No' }}</div>
    </td>
  </tr>
</table>
@endif

{{-- Signatures --}}
<div class="phase-header" style="margin-top:12px;">Certifications &amp; Approvals</div>
<table style="border:none;">
  <tr style="border:none;">
    <td style="border:none; width:33%; text-align:center; padding:0 6px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($dv->preparedByUser)->name ?? '________________________' }}</div>
        <div class="label">Prepared by (End User)</div>
        @if($dv->dv_prepared_at)
        <div class="label" style="color:#555;">{{ $dv->dv_prepared_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="border:none; width:33%; text-align:center; padding:0 6px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($dv->divisionChief)->name ?? '________________________' }}</div>
        <div class="label">Division Chief (eLog)</div>
        @if($dv->division_chief_elog_at)
        <div class="label" style="color:#555;">{{ $dv->division_chief_elog_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="border:none; width:33%; text-align:center; padding:0 6px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($dv->bookkeeper)->name ?? '________________________' }}</div>
        <div class="label">Bookkeeper</div>
        @if($dv->bookkeeper_at)
        <div class="label" style="color:#555;">{{ $dv->bookkeeper_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
  </tr>
</table>
<table style="border:none; margin-top:8px;">
  <tr style="border:none;">
    <td style="border:none; width:33%; text-align:center; padding:0 6px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($dv->accountant)->name ?? '________________________' }}</div>
        <div class="label">Accountant</div>
        @if($dv->accountant_at)
        <div class="label" style="color:#555;">{{ $dv->accountant_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="border:none; width:33%; text-align:center; padding:0 6px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($dv->ocdSigner)->name ?? '________________________' }}</div>
        <div class="label">Campus Director (Approving Authority)</div>
        @if($dv->ocd_sign_at)
        <div class="label" style="color:#555;">{{ $dv->ocd_sign_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="border:none; width:33%; text-align:center; padding:0 6px;">
      <div style="min-height:30px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($dv->cashier)->name ?? '________________________' }}</div>
        <div class="label">Cashier</div>
        @if($dv->cashier_processed_at)
        <div class="label" style="color:#555;">{{ $dv->cashier_processed_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
  </tr>
</table>

{{-- Payment info --}}
@if($dv->payment_released_at || $dv->payment_reference)
<table style="margin-top:10px;">
  <tr>
    <td style="width:50%;">
      <div class="label">Payment Released:</div>
      <div class="value">{{ optional($dv->payment_released_at)?->format('m/d/Y') ?? '—' }}</div>
    </td>
    <td style="width:50%;">
      <div class="label">Cheque/ADA Reference No.:</div>
      <div class="value">{{ $dv->payment_reference ?: '—' }}</div>
    </td>
  </tr>
  <tr>
    <td>
      <div class="label">Amount Paid (₱):</div>
      <div class="value">₱ {{ number_format($dv->cashier_amount ?? $dv->net_amount ?? 0, 2) }}</div>
    </td>
    <td>
      <div class="label">Status:</div>
      <div class="value" style="color:{{ $dv->status === 'released' ? '#16a34a' : ($dv->status === 'returned_bookkeeper' || $dv->status === 'returned_accountant' ? '#dc2626' : '#92400e') }};">
        {{ strtoupper(str_replace('_', ' ', $dv->status)) }}
      </div>
    </td>
  </tr>
</table>
@else
<table style="margin-top:10px;">
  <tr>
    <td>
      <div class="label">Status:</div>
      <div class="value" style="color:{{ $dv->status === 'released' ? '#16a34a' : ($dv->status === 'returned_bookkeeper' || $dv->status === 'returned_accountant' ? '#dc2626' : '#92400e') }};">
        {{ strtoupper(str_replace('_', ' ', $dv->status)) }}
      </div>
    </td>
  </tr>
</table>
@endif

<div style="margin-top:14px; border-top:1px solid #ddd; padding-top:6px; font-size:7.5pt; color:#666; text-align:center;">
  This is a digital record from the PSHS-CRC Management Information System.
  Scan the QR code to verify at {{ config('app.url') }}/dv/{{ $dv->id }}
</div>

</body>
</html>
