<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; font-size: 9pt; margin: 0; }
  h2 { font-size: 13pt; text-align: center; margin: 0 0 2px; }
  .subtitle { font-size: 8pt; text-align: center; margin-bottom: 8px; }
  .form-no { font-size: 7pt; text-align: right; margin-bottom: 2px; }
  .header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  .header-table td { font-size: 8.5pt; padding: 2px 4px; vertical-align: top; }
  .label { font-size: 7.5pt; color: #555; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
  table.items th { background: #f0f0f0; font-size: 8pt; padding: 4px 6px; border: 1px solid #999; text-align: center; }
  table.items td { font-size: 8pt; padding: 3px 6px; border: 1px solid #ccc; vertical-align: top; }
  table.items td.right { text-align: right; }
  table.items td.center { text-align: center; }
  .total-row td { font-weight: bold; background: #f9f9f9; }
  .signatures { width: 100%; margin-top: 16px; }
  .signatures td { width: 25%; vertical-align: top; font-size: 8pt; padding: 4px; }
  .sig-line { border-top: 1px solid #333; margin-top: 28px; margin-bottom: 2px; }
  .footer-note { font-size: 7pt; color: #666; margin-top: 8px; }
</style>
</head>
<body>

<div class="form-no">GAM Form No. 13</div>
<h2>REQUISITION AND ISSUE SLIP (RIS)</h2>
<div class="subtitle">Philippine Science High School – Caraga Region Campus</div>

<table class="header-table">
  <tr>
    <td width="40%">
      <span class="label">RIS Number: </span>
      <strong>{{ $ris->ris_number }}</strong>
    </td>
    <td width="30%">
      <span class="label">Division/Office: </span>{{ $ris->division?->division_name ?? '—' }}
    </td>
    <td width="30%" style="text-align:right;">
      <span class="label">Date Requested: </span>{{ $ris->date_requested?->format('F j, Y') ?? '—' }}
    </td>
  </tr>
  <tr>
    <td colspan="2">
      <span class="label">Purpose: </span>{{ $ris->purpose }}
    </td>
    <td style="text-align:right;">
      <span class="label">Date Needed: </span>{{ $ris->date_needed?->format('F j, Y') ?? '—' }}
    </td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th style="width: 4%">No.</th>
      <th style="width: 10%">Stock No.</th>
      <th style="width: 30%">Description</th>
      <th style="width: 8%">Unit</th>
      <th style="width: 11%">Qty Requested</th>
      <th style="width: 11%">Qty Issued</th>
      <th style="width: 11%">Unit Cost</th>
      <th style="width: 15%">Total Cost</th>
    </tr>
  </thead>
  <tbody>
    @php $totalCost = 0; @endphp
    @foreach($ris->items as $i => $item)
    @php $totalCost += $item->totalCost(); @endphp
    <tr>
      <td class="center">{{ $i + 1 }}</td>
      <td class="center">{{ $item->supplyItem?->item_code ?? '—' }}</td>
      <td>{{ $item->supplyItem?->description ?? '—' }}</td>
      <td class="center">{{ $item->supplyItem?->unit ?? '—' }}</td>
      <td class="right">{{ number_format($item->quantity_requested, 3) }}</td>
      <td class="right">{{ number_format($item->quantity_issued, 3) }}</td>
      <td class="right">{{ number_format($item->unit_cost, 2) }}</td>
      <td class="right">{{ number_format($item->totalCost(), 2) }}</td>
    </tr>
    @endforeach
    @for($j = count($ris->items); $j < 8; $j++)
    <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
    <tr class="total-row">
      <td colspan="7" class="right">TOTAL:</td>
      <td class="right">{{ number_format($totalCost, 2) }}</td>
    </tr>
  </tbody>
</table>

@if($ris->remarks)
<p style="font-size:8pt;margin-top:6px;"><strong>Remarks:</strong> {{ $ris->remarks }}</p>
@endif
@if($ris->approval_remarks)
<p style="font-size:8pt;margin-top:2px;"><strong>Approval Remarks:</strong> {{ $ris->approval_remarks }}</p>
@endif

<table class="signatures">
  <tr>
    <td>
      <strong>REQUESTED BY:</strong>
      <div class="sig-line"></div>
      <strong>{{ $ris->requester?->name ?? '________________________' }}</strong><br>
      <span class="label">Requesting Officer</span><br>
      <span class="label">{{ $ris->date_requested?->format('F j, Y') ?? '' }}</span>
    </td>
    <td>
      <strong>APPROVED BY:</strong>
      <div class="sig-line"></div>
      <strong>{{ $ris->approver?->name ?? '________________________' }}</strong><br>
      <span class="label">Approving Authority</span><br>
      <span class="label">{{ $ris->approved_at?->format('F j, Y') ?? '' }}</span>
    </td>
    <td>
      <strong>ISSUED BY:</strong>
      <div class="sig-line"></div>
      <strong>{{ $ris->issuer?->name ?? '________________________' }}</strong><br>
      <span class="label">Supply / Property Officer</span><br>
      <span class="label">{{ $ris->issued_at?->format('F j, Y') ?? '' }}</span>
    </td>
    <td>
      <strong>RECEIVED BY:</strong>
      <div class="sig-line"></div>
      <strong>________________________</strong><br>
      <span class="label">Requisitioner</span><br>
      <span class="label">Date: ________________</span>
    </td>
  </tr>
</table>

<div class="footer-note">
  RIS Status: <strong>{{ $ris->statusLabel() }}</strong> &nbsp;|&nbsp;
  Generated: {{ now()->format('F j, Y g:i A') }} &nbsp;|&nbsp;
  This document is computer-generated. | GAM Form No. 13 (COA Circular 2015-010)
</div>

</body>
</html>
