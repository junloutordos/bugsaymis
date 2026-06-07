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
.no-border td { border:none; }
.label { font-size:7.5pt; color:#333; }
.value { font-size:9pt; font-weight:bold; }
.center { text-align:center; }
.right { text-align:right; }
.items-table th { background:#f0f0f0; font-size:8pt; text-align:center; }
.items-table td { font-size:8.5pt; }
.total-row td { font-weight:bold; background:#f9f9f9; }
.sig-block { text-align:center; padding-top:4px; }
.sig-name { font-weight:bold; font-size:9pt; }
.sig-title { font-size:7.5pt; }
.sig-line { border-top:1px solid #000; margin-top:22px; padding-top:3px; }
.qr-box { text-align:right; font-size:7pt; color:#555; }
.header-agency { font-size:9pt; text-align:center; margin-bottom:3px; }
.form-no { font-size:7.5pt; text-align:right; color:#555; margin-bottom:4px; }
.digital-ref { font-size:7.5pt; color:#1447c0; text-align:right; margin-top:4px; }
.status-issued { color:#155724; font-weight:bold; }
.status-draft { color:#856404; }
</style>
</head>
<body>

{{-- Header --}}
<table class="no-border" style="margin-bottom:6px;">
  <tr>
    <td style="width:70%;">
      <div class="header-agency">Republic of the Philippines</div>
      <div class="header-agency"><strong>Philippine Science High School – Caraga Region Campus</strong></div>
      <div class="header-agency">Ampayon, Butuan City, Agusan del Norte</div>
    </td>
    <td style="width:30%; text-align:right; vertical-align:top;">
      <div class="qr-box">
        <img src="data:image/svg+xml;base64,{{ $qrSvg }}" width="70" height="70" alt="QR" /><br>
        <span class="digital-ref">Digital Record Ref</span>
      </div>
    </td>
  </tr>
</table>

<div class="form-no">APP Form / PO Form</div>
<div style="text-align:center; margin-bottom:6px;">
  <h1>Purchase Order</h1>
  @if($po->status === 'issued')
    <div class="status-issued" style="font-size:8.5pt;">ISSUED</div>
  @else
    <div class="status-draft" style="font-size:8.5pt;">{{ strtoupper(str_replace('_',' ',$po->status)) }}</div>
  @endif
</div>

{{-- PO meta --}}
<table style="margin-bottom:4px;">
  <tr>
    <td style="width:40%;">
      <div class="label">PO No.:</div>
      <div class="value">{{ $po->po_number ?: '(Not yet issued)' }}</div>
    </td>
    <td style="width:30%;">
      <div class="label">Date:</div>
      <div class="value">{{ $po->po_date?->format('m/d/Y') ?? '—' }}</div>
    </td>
    <td style="width:30%;">
      <div class="label">PR Reference:</div>
      <div class="value">{{ optional($po->pr)->pr_no ?? '—' }}</div>
    </td>
  </tr>
</table>

{{-- Supplier info --}}
<table style="margin-bottom:4px;">
  <tr>
    <td style="width:60%;">
      <div class="label">Supplier / Company Name:</div>
      <div class="value">{{ $po->supplier_name }}</div>
    </td>
    <td style="width:40%;">
      <div class="label">TIN:</div>
      <div class="value">{{ $po->supplier_tin ?: '—' }}</div>
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <div class="label">Address:</div>
      <div class="value">{{ $po->supplier_address ?: '—' }}</div>
    </td>
  </tr>
</table>

{{-- Terms --}}
<table style="margin-bottom:4px;">
  <tr>
    <td style="width:33%;">
      <div class="label">Delivery Place:</div>
      <div class="value">{{ $po->delivery_place ?: '—' }}</div>
    </td>
    <td style="width:33%;">
      <div class="label">Delivery Date:</div>
      <div class="value">{{ $po->delivery_date?->format('m/d/Y') ?? '—' }}</div>
    </td>
    <td style="width:34%;">
      <div class="label">Payment Terms:</div>
      <div class="value">{{ $po->payment_terms ?: '—' }}</div>
    </td>
  </tr>
  <tr>
    <td colspan="3">
      <div class="label">Delivery Terms:</div>
      <div class="value">{{ $po->delivery_terms ?: '—' }}</div>
    </td>
  </tr>
</table>

{{-- Gentleman phrase --}}
<div style="margin:6px 0; font-size:9pt;">
  Gentlemen: Please furnish this office the following articles subject to the terms and conditions contained herein:
</div>

{{-- Line items --}}
<table class="items-table" style="margin-bottom:4px;">
  <thead>
    <tr>
      <th style="width:6%;">Item No.</th>
      <th style="width:10%;">Unit</th>
      <th>Description</th>
      <th style="width:8%;">Qty</th>
      <th style="width:14%;">Unit Cost</th>
      <th style="width:14%;">Total Cost</th>
    </tr>
  </thead>
  <tbody>
    @foreach($po->items as $item)
    <tr>
      <td class="center">{{ $item->item_no }}</td>
      <td class="center">{{ $item->unit ?: '—' }}</td>
      <td>{{ $item->description }}</td>
      <td class="center">{{ number_format($item->quantity) }}</td>
      <td class="right">{{ number_format($item->unit_cost, 2) }}</td>
      <td class="right">{{ number_format($item->total_cost, 2) }}</td>
    </tr>
    @endforeach
    {{-- Filler rows --}}
    @for($i = count($po->items); $i < 5; $i++)
    <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
    <tr class="total-row">
      <td colspan="5" class="right">TOTAL AMOUNT:</td>
      <td class="right">₱ {{ number_format($po->total_amount, 2) }}</td>
    </tr>
  </tbody>
</table>

@if($po->remarks)
<table style="margin-bottom:6px;">
  <tr>
    <td><div class="label">Remarks/Special Instructions:</div><div>{{ $po->remarks }}</div></td>
  </tr>
</table>
@endif

{{-- Signatories --}}
<table class="no-border" style="margin-top:16px;">
  <tr>
    <td style="width:33%; text-align:center; padding:0 4px;">
      <div class="label" style="font-size:7.5pt;">Prepared by:</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($po->creator)->name }}</div>
        <div class="sig-title">Requesting Party</div>
        @if($po->created_at)
          <div class="sig-title">{{ $po->created_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="width:33%; text-align:center; padding:0 4px;">
      <div class="label" style="font-size:7.5pt;">Reviewed by:</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($po->procurementOfficer)->name ?? ' ' }}</div>
        <div class="sig-title">Procurement Officer</div>
        @if($po->procurement_officer_at)
          <div class="sig-title">{{ $po->procurement_officer_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
    <td style="width:34%; text-align:center; padding:0 4px;">
      <div class="label" style="font-size:7.5pt;">Approved by:</div>
      <div class="sig-line">
        <div class="sig-name">{{ optional($po->ocd)->name ?? ' ' }}</div>
        <div class="sig-title">Campus Director</div>
        @if($po->ocd_at)
          <div class="sig-title">{{ $po->ocd_at->format('m/d/Y') }}</div>
        @endif
      </div>
    </td>
  </tr>
</table>

</body>
</html>
