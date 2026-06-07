<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,sans-serif; font-size:9pt; color:#000; line-height:1.4; }
table { width:100%; border-collapse:collapse; }
td,th { vertical-align:top; }
.no-border td { border:none; }
.label { font-size:7.5pt; color:#555; }
.value { font-size:9pt; font-weight:bold; }
.center { text-align:center; }
.right { text-align:right; }
.border-all td, .border-all th { border:1px solid #000; padding:3px 5px; }
.items-th { background:#e8e8e8; font-size:8pt; text-align:center; font-weight:bold; }
.items-td { font-size:8.5pt; padding:3px 5px; border:1px solid #000; }
.header-agency { font-size:9pt; text-align:center; }
.form-title { font-size:11pt; font-weight:bold; text-align:center; text-transform:uppercase; margin:6px 0 2px; }
.qr-box { text-align:right; font-size:7pt; color:#555; }
.sig-line { border-top:1px solid #000; margin-top:30px; padding-top:2px; }
.instructions { font-size:7.5pt; color:#333; margin:8px 0; }
.instructions li { margin-bottom:2px; }
.total-row td { font-weight:bold; background:#f5f5f5; }
</style>
</head>
<body>

<table class="no-border" style="margin-bottom:6px;">
  <tr>
    <td style="width:70%;">
      <div class="header-agency">Republic of the Philippines</div>
      <div class="header-agency"><strong>Philippine Science High School – Caraga Region Campus</strong></div>
      <div class="header-agency">Ampayon, Butuan City, Agusan del Norte</div>
    </td>
    <td style="width:30%; text-align:right; vertical-align:top;">
      <div class="qr-box">
        <img src="data:image/svg+xml;base64,{{ $qrSvg }}" width="65" height="65" alt="QR" /><br>
        <span style="font-size:7pt; color:#1447c0;">Digital Record Ref</span>
      </div>
    </td>
  </tr>
</table>

<div class="form-title">Request for Quotation</div>
<div style="font-size:7.5pt;text-align:center;margin-bottom:8px;">
  (In accordance with Section 52/53 of RA 9184 and its IRR)
</div>

{{-- RFQ Header --}}
<table class="border-all" style="margin-bottom:8px;">
  <tr>
    <td style="width:25%"><span class="label">RFQ No.:</span><br><span class="value">{{ $rfq->rfq_number }}</span></td>
    <td style="width:25%"><span class="label">Date:</span><br><span class="value">{{ $rfq->rfq_date?->format('F d, Y') }}</span></td>
    <td style="width:25%"><span class="label">PR Reference:</span><br><span class="value">{{ $rfq->pr?->assigned_pr_number ?: $rfq->pr?->pr_no }}</span></td>
    <td style="width:25%"><span class="label">Validity Period:</span><br><span class="value">{{ $rfq->validity_days }} day(s) from date of receipt</span></td>
  </tr>
  <tr>
    <td colspan="2"><span class="label">Purpose / Project:</span><br><span class="value">{{ $rfq->purpose }}</span></td>
    <td><span class="label">Delivery Place:</span><br><span class="value">{{ $rfq->delivery_place ?? '—' }}</span></td>
    <td><span class="label">Payment Terms:</span><br><span class="value">{{ $rfq->payment_terms ?? '—' }}</span></td>
  </tr>
  <tr>
    <td colspan="2"><span class="label">End-User / Unit:</span><br><span class="value">{{ $rfq->pr?->division?->division_name ?? '—' }}</span></td>
    <td colspan="2"><span class="label">Prepared by:</span><br><span class="value">{{ $rfq->creator?->name ?? '—' }}</span></td>
  </tr>
</table>

{{-- Instructions --}}
<div class="instructions">
  <strong>Instructions to Suppliers:</strong>
  <ol>
    <li>Accomplish this Request for Quotation (RFQ) completely and accurately. Failure to observe this may result in disqualification.</li>
    <li>Unit price should include all applicable taxes, delivery charges, and other costs.</li>
    <li>Quotations must be signed by authorized representative and submitted on or before the validity date.</li>
    <li>Prices quoted herein shall remain valid and irrevocable for the period stated above.</li>
    <li>The Procuring Entity reserves the right to award the contract per item (split award).</li>
  </ol>
</div>

{{-- Items Table --}}
<table style="margin-bottom:10px;">
  <thead>
    <tr>
      <th class="items-th" style="width:5%">Item No.</th>
      <th class="items-th" style="width:8%">Unit</th>
      <th class="items-th" style="width:8%">Qty</th>
      <th class="items-th" style="width:35%">Description / Specifications</th>
      <th class="items-th" style="width:12%">ABC<br>(per unit)</th>
      <th class="items-th" style="width:12%">Unit Price<br>Quoted</th>
      <th class="items-th" style="width:10%">Brand/<br>Model</th>
      <th class="items-th" style="width:10%">Total<br>Amount</th>
    </tr>
  </thead>
  <tbody>
    @php $grandAbc = 0; @endphp
    @foreach($rfq->items as $item)
      @php $grandAbc += $item->abc; @endphp
      <tr>
        <td class="items-td center">{{ $item->item_no }}</td>
        <td class="items-td center">{{ $item->unit }}</td>
        <td class="items-td center">{{ $item->quantity }}</td>
        <td class="items-td">{{ $item->description }}</td>
        <td class="items-td right">{{ $item->abc > 0 ? number_format($item->abc / max(1,$item->quantity), 2) : '—' }}</td>
        <td class="items-td">&nbsp;</td>
        <td class="items-td">&nbsp;</td>
        <td class="items-td">&nbsp;</td>
      </tr>
    @endforeach
    <tr class="total-row">
      <td class="items-td" colspan="4" style="text-align:right">APPROVED BUDGET FOR THE CONTRACT (ABC):</td>
      <td class="items-td right" colspan="4">₱ {{ number_format($grandAbc, 2) }}</td>
    </tr>
  </tbody>
</table>

{{-- Supplier Declaration --}}
<table class="border-all" style="margin-bottom:12px; font-size:8.5pt;">
  <tr>
    <td colspan="2" style="background:#f5f5f5; font-weight:bold; font-size:9pt;">SUPPLIER'S QUOTATION</td>
  </tr>
  <tr>
    <td style="width:50%">
      <span class="label">Business Name:</span><br>
      <div style="border-bottom:1px solid #000; min-height:18px; margin-top:4px;">&nbsp;</div>
    </td>
    <td style="width:50%">
      <span class="label">TIN:</span><br>
      <div style="border-bottom:1px solid #000; min-height:18px; margin-top:4px;">&nbsp;</div>
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <span class="label">Business Address:</span><br>
      <div style="border-bottom:1px solid #000; min-height:18px; margin-top:4px;">&nbsp;</div>
    </td>
  </tr>
  <tr>
    <td colspan="2" style="padding-top:4px; font-size:8pt;">
      <em>I/We hereby certify that the above quotation is correct and I/We can deliver the items within the period
      specified above upon receipt of the Purchase Order. The prices quoted shall remain valid and irrevocable
      during the validity period.</em>
    </td>
  </tr>
  <tr>
    <td style="padding-top:20px;">
      <div class="sig-line">Signature over Printed Name</div>
      <div class="label">Authorized Representative</div>
    </td>
    <td style="padding-top:20px;">
      <div class="sig-line">Position / Designation</div>
      &nbsp;
    </td>
  </tr>
  <tr>
    <td><div class="sig-line">Date</div></td>
    <td><div class="sig-line">Contact Number / Email</div></td>
  </tr>
</table>

{{-- Agency Sign-off --}}
<table class="no-border" style="font-size:8pt;">
  <tr>
    <td style="width:50%">
      <strong>For the Procuring Entity:</strong><br><br>
      <div class="sig-line" style="max-width:220px;">&nbsp;</div>
      <strong>{{ $rfq->creator?->name ?? '___________________________' }}</strong><br>
      BAC Secretariat / Procurement Officer<br>
      Date Issued: {{ $rfq->rfq_date?->format('m/d/Y') }}
    </td>
    <td style="width:50%; text-align:right; font-size:7.5pt; color:#555;">
      GPPB Form — RFQ<br>
      Revised per RA 9184 IRR 2016<br>
      {{ $rfq->rfq_number }}
    </td>
  </tr>
</table>

</body>
</html>
