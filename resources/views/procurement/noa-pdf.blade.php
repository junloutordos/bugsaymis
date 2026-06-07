<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:Arial,sans-serif; font-size:10pt; color:#000; line-height:1.6; }
table { width:100%; border-collapse:collapse; }
td,th { vertical-align:top; }
.no-border td { border:none; padding:2px 0; }
.border-all td, .border-all th { border:1px solid #000; padding:4px 6px; }
.header-agency { font-size:10pt; text-align:center; }
.items-th { background:#e8e8e8; font-size:9pt; text-align:center; font-weight:bold; border:1px solid #000; padding:4px 6px; }
.items-td { font-size:9.5pt; padding:4px 6px; border:1px solid #000; }
.total-row td { font-weight:bold; background:#f0f0f0; }
.right { text-align:right; }
.center { text-align:center; }
.sig-line { border-top:1px solid #000; margin-top:35px; padding-top:3px; }
.qr-box { text-align:right; font-size:7pt; color:#555; }
</style>
</head>
<body>

<table class="no-border" style="margin-bottom:10px;">
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

<div style="text-align:center;font-size:13pt;font-weight:bold;text-transform:uppercase;margin-bottom:12px;">
  Notice of Award
</div>

<table class="no-border" style="margin-bottom:12px;font-size:9.5pt;">
  <tr>
    <td style="width:20%"><strong>Date:</strong></td>
    <td>{{ $rfq->awarded_at?->format('F d, Y') ?? now()->format('F d, Y') }}</td>
    <td style="width:20%; text-align:right"><strong>NOA Ref.:</strong></td>
    <td style="text-align:right">NOA-{{ $rfq->rfq_number }}</td>
  </tr>
  <tr>
    <td><strong>RFQ No.:</strong></td>
    <td>{{ $rfq->rfq_number }}</td>
    <td style="text-align:right"><strong>PR Ref.:</strong></td>
    <td style="text-align:right">{{ $rfq->pr?->assigned_pr_number ?: $rfq->pr?->pr_no }}</td>
  </tr>
</table>

<p style="margin-bottom:12px;font-size:9.5pt;">
  Dear <strong>{{ $supplier->supplier_name }}</strong>,
</p>

<p style="margin-bottom:12px;font-size:9.5pt;">
  After evaluation of quotations received in response to our Request for Quotation No. <strong>{{ $rfq->rfq_number }}</strong>
  dated <strong>{{ $rfq->rfq_date?->format('F d, Y') }}</strong>, your firm has been determined to have offered the
  <strong>Lowest Calculated and Responsive Quotation (LCRQ)</strong> for the following items:
</p>

{{-- Awarded Items Table --}}
<table class="border-all" style="margin-bottom:14px; font-size:9.5pt;">
  <thead>
    <tr>
      <th class="items-th" style="width:6%">Item</th>
      <th class="items-th" style="width:8%">Unit</th>
      <th class="items-th" style="width:8%">Qty</th>
      <th class="items-th">Description</th>
      <th class="items-th" style="width:14%">Unit Price</th>
      <th class="items-th" style="width:14%">Total Amount</th>
    </tr>
  </thead>
  <tbody>
    @php $grandTotal = 0; @endphp
    @foreach($awardedQuotations as $iq)
      @php $grandTotal += $iq->total_cost; @endphp
      <tr>
        <td class="items-td center">{{ $iq->item->item_no }}</td>
        <td class="items-td center">{{ $iq->item->unit }}</td>
        <td class="items-td center">{{ $iq->item->quantity }}</td>
        <td class="items-td">{{ $iq->item->description }}</td>
        <td class="items-td right">₱ {{ number_format($iq->unit_cost, 2) }}</td>
        <td class="items-td right">₱ {{ number_format($iq->total_cost, 2) }}</td>
      </tr>
    @endforeach
    <tr class="total-row">
      <td class="items-td" colspan="5" style="text-align:right">TOTAL CONTRACT PRICE:</td>
      <td class="items-td right">₱ {{ number_format($grandTotal, 2) }}</td>
    </tr>
  </tbody>
</table>

<p style="margin-bottom:8px;font-size:9.5pt;">
  <strong>Delivery Terms:</strong> {{ $rfq->validity_days }} days upon receipt of Purchase Order<br>
  <strong>Payment Terms:</strong> {{ $rfq->payment_terms ?? '30 days after acceptance' }}<br>
  <strong>Delivery Place:</strong> {{ $rfq->delivery_place ?? 'PSHS-CRC, Ampayon, Butuan City' }}
</p>

<p style="margin-bottom:12px;font-size:9.5pt;">
  You are hereby requested to submit the following documents prior to the issuance of the Purchase Order:
</p>
<ol style="font-size:9.5pt;margin-left:20px;margin-bottom:16px;">
  <li>Mayor's Permit / Business License (latest)</li>
  <li>PhilGEPS Registration Number / Certificate</li>
  <li>Omnibus Sworn Statement (for contracts above ₱50,000)</li>
  <li>Income/Business Tax Return (for contracts ₱500,000 and above)</li>
</ol>

<table class="no-border" style="margin-top:20px;font-size:9.5pt;">
  <tr>
    <td style="width:50%">
      For the Procuring Entity:<br><br>
      <div class="sig-line" style="max-width:200px;">&nbsp;</div>
      <strong>BAC Chairperson / Authorized Representative</strong><br>
      Date: _______________
    </td>
    <td style="width:50%; text-align:center; padding-top:50px;">
      Received and acknowledged by:<br><br>
      <div class="sig-line" style="max-width:200px; margin:0 auto;">&nbsp;</div>
      <strong>{{ $supplier->supplier_name }}</strong><br>
      Authorized Representative<br>
      Date: _______________
    </td>
  </tr>
</table>

</body>
</html>
