<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial, sans-serif; font-size:9pt; color:#000; line-height:1.4; }
h1 { font-size:11pt; font-weight:bold; text-align:center; text-transform:uppercase; }
h2 { font-size:9pt; font-weight:bold; text-align:center; }
.form-title { text-align:center; margin-bottom:6px; }
table { width:100%; border-collapse:collapse; }
td, th { border:1px solid #000; padding:4px 6px; vertical-align:top; }
.no-border td, .no-border th { border:none; }
.label { font-size:7.5pt; color:#333; }
.value { font-size:9pt; font-weight:bold; }
.center { text-align:center; }
.right { text-align:right; }
.sig-line { border-top:1px solid #000; margin-top:20px; padding-top:2px; font-size:8pt; text-align:center; }
.sig-name { font-weight:bold; font-size:9pt; }
.items-table th { background:#f0f0f0; font-size:8pt; text-align:center; }
.items-table td { font-size:8.5pt; }
.total-row td { font-weight:bold; background:#f9f9f9; }
.qr-box { text-align:right; font-size:7pt; color:#555; }
.header-agency { font-size:9pt; text-align:center; margin-bottom:4px; }
.form-no { font-size:7.5pt; text-align:right; color:#555; margin-bottom:4px; }
.digital-ref { font-size:7.5pt; color:#1447c0; text-align:right; margin-top:4px; }
</style>
</head>
<body>

{{-- QR Code --}}
<table style="border:none; margin-bottom:6px;">
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

<div class="form-no">APP Form 2 / PR Form</div>
<div class="form-title">
  <h1>Purchase Request</h1>
</div>

{{-- Header info --}}
<table style="margin-bottom:0;">
  <tr>
    <td style="width:50%;">
      <div class="label">Division/Office:</div>
      <div class="value">{{ optional($pr->division)->division_name ?? '—' }}</div>
    </td>
    <td style="width:25%;">
      <div class="label">PR No.:</div>
      <div class="value">{{ $pr->assigned_pr_number ?: ($pr->pr_no ?: '—') }}</div>
    </td>
    <td style="width:25%;">
      <div class="label">Date:</div>
      <div class="value">{{ optional($pr->pr_date)->format('m/d/Y') ?? '—' }}</div>
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <div class="label">Purpose/Description:</div>
      <div class="value">{{ $pr->purpose }}</div>
    </td>
    <td>
      <div class="label">Fund Source:</div>
      <div class="value">{{ $pr->fund_source ?: 'General Fund' }}</div>
    </td>
  </tr>
  <tr>
    <td>
      <div class="label">Requested by:</div>
      <div class="value">{{ optional($pr->requester)->name ?? '—' }}</div>
    </td>
    <td>
      <div class="label">Supplemental PR:</div>
      <div class="value">{{ $pr->is_supplemental ? 'YES' : 'No' }}</div>
    </td>
    <td>
      <div class="label">PPMP Checked:</div>
      <div class="value">{{ $pr->ppmp_checked ? 'Yes' : 'NO' }}</div>
    </td>
  </tr>
</table>

{{-- Items --}}
<table class="items-table" style="margin-top:0;">
  <thead>
    <tr>
      <th style="width:6%;">Item No.</th>
      <th style="width:10%;">Unit</th>
      <th style="width:38%;">Item Description / Specifications</th>
      <th style="width:10%;">Qty</th>
      <th style="width:18%;">Unit Cost (₱)</th>
      <th style="width:18%;">Total Cost (₱)</th>
    </tr>
  </thead>
  <tbody>
    @forelse($pr->items as $i => $item)
    <tr>
      <td class="center">{{ $i + 1 }}</td>
      <td class="center">{{ $item->unit }}</td>
      <td>{{ $item->item_description }}</td>
      <td class="center">{{ $item->quantity }}</td>
      <td class="right">{{ number_format($item->unit_cost, 2) }}</td>
      <td class="right">{{ number_format($item->total_cost ?? ($item->unit_cost * $item->quantity), 2) }}</td>
    </tr>
    @empty
    <tr><td colspan="6" class="center" style="color:#999;">No items</td></tr>
    @endforelse
    <tr class="total-row">
      <td colspan="5" class="right"><strong>TOTAL AMOUNT:</strong></td>
      <td class="right"><strong>₱ {{ number_format($pr->items->sum(fn($i) => $i->total_cost ?? ($i->unit_cost * $i->quantity)), 2) }}</strong></td>
    </tr>
  </tbody>
</table>

{{-- Signatures --}}
<table style="margin-top:20px; border:none;">
  <tr style="border:none;">
    <td style="border:none; width:33%; text-align:center; padding:0 8px;">
      <div style="min-height:35px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($pr->requester)->name ?? '________________________' }}</div>
        <div class="label">Requested by / End User</div>
        @if($pr->division_chief_at)
        <div class="label" style="color:#555;">Date: {{ $pr->division_chief_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="border:none; width:33%; text-align:center; padding:0 8px;">
      <div style="min-height:35px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($pr->divisionChief)->name ?? '________________________' }}</div>
        <div class="label">Division Chief</div>
        @if($pr->division_chief_at)
        <div class="label" style="color:#555;">{{ $pr->division_chief_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="border:none; width:33%; text-align:center; padding:0 8px;">
      <div style="min-height:35px;">&nbsp;</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($pr->ocd)->name ?? '________________________' }}</div>
        <div class="label">Campus Director / Approving Authority</div>
        @if($pr->ocd_at)
        <div class="label" style="color:#555;">{{ $pr->ocd_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
  </tr>
</table>

{{-- Procurement Officer --}}
@if($pr->procurementOfficer || $pr->assigned_pr_number)
<table style="margin-top:12px; border:none;">
  <tr style="border:none;">
    <td style="border:none; width:50%; padding:0 8px;">
      <div class="label">PR Number Assigned by:</div>
      <div class="sig-line" style="margin-top:4px;">
        <div class="sig-name">{{ optional($pr->procurementOfficer)->name ?? '________________________' }}</div>
        <div class="label">Procurement Officer</div>
        @if($pr->procurement_officer_at)
        <div class="label" style="color:#555;">{{ $pr->procurement_officer_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="border:none; width:50%; padding:0 8px;">
      <div class="label">Status:</div>
      <div class="value" style="font-size:10pt; color:{{ $pr->status === 'approved' ? '#16a34a' : ($pr->status === 'rejected' ? '#dc2626' : '#92400e') }};">
        {{ strtoupper(str_replace('_', ' ', $pr->status)) }}
      </div>
    </td>
  </tr>
</table>
@endif

{{-- Digital reference footer --}}
<div style="margin-top:16px; border-top:1px solid #ddd; padding-top:6px; font-size:7.5pt; color:#666; text-align:center;">
  This is a digital record of the Purchase Request from the PSHS-CRC Management Information System.
  Scan the QR code to verify authenticity at {{ config('app.url') }}/procurements/{{ $pr->id }}
</div>

</body>
</html>
