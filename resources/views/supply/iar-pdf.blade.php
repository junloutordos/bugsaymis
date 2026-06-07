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
  .value { border-bottom: 1px solid #333; min-width: 120px; display: inline-block; }
  table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
  table.items th { background: #f0f0f0; font-size: 8pt; padding: 4px 6px; border: 1px solid #999; text-align: center; }
  table.items td { font-size: 8pt; padding: 3px 6px; border: 1px solid #ccc; vertical-align: top; }
  table.items td.right { text-align: right; }
  table.items td.center { text-align: center; }
  .total-row td { font-weight: bold; background: #f9f9f9; }
  .signatures { width: 100%; margin-top: 16px; }
  .signatures td { width: 33%; vertical-align: top; font-size: 8pt; padding: 4px; }
  .sig-line { border-top: 1px solid #333; margin-top: 28px; margin-bottom: 2px; }
  .footer-note { font-size: 7pt; color: #666; margin-top: 8px; }
  .section-header { background: #e8e8e8; font-weight: bold; font-size: 8pt; padding: 3px 6px; border: 1px solid #999; }
</style>
</head>
<body>

<div class="form-no">GAM Form No. 5</div>
<h2>INSPECTION AND ACCEPTANCE REPORT</h2>
<div class="subtitle">Philippine Science High School – Caraga Region Campus</div>

<table class="header-table">
  <tr>
    <td width="50%">
      <span class="label">IAR Number: </span>
      <strong>{{ $iar->iar_number }}</strong>
    </td>
    <td width="50%" style="text-align: right;">
      <span class="label">Date of Delivery: </span>
      <strong>{{ $iar->delivery_date?->format('F j, Y') ?? '—' }}</strong>
    </td>
  </tr>
  <tr>
    <td>
      <span class="label">Supplier: </span>{{ $iar->supplier_name }}
      @if($iar->supplier_tin)
        &nbsp;&nbsp;<span class="label">TIN: </span>{{ $iar->supplier_tin }}
      @endif
    </td>
    <td style="text-align: right;">
      <span class="label">PO/Reference: </span>{{ $iar->purchaseOrder?->po_number ?? 'N/A' }}
    </td>
  </tr>
  @if($iar->supplier_address)
  <tr>
    <td colspan="2">
      <span class="label">Address: </span>{{ $iar->supplier_address }}
    </td>
  </tr>
  @endif
  <tr>
    <td>
      <span class="label">Date Inspected: </span>{{ $iar->inspection_date?->format('F j, Y') ?? '—' }}
    </td>
    <td style="text-align: right;">
      <span class="label">Inspector: </span>{{ $iar->inspector?->name ?? '—' }}
    </td>
  </tr>
</table>

<table class="items">
  <thead>
    <tr>
      <th style="width: 4%">No.</th>
      <th style="width: 28%">Description</th>
      <th style="width: 7%">Unit</th>
      <th style="width: 10%">Qty Ordered</th>
      <th style="width: 10%">Qty Delivered</th>
      <th style="width: 10%">Qty Accepted</th>
      <th style="width: 10%">Qty Rejected</th>
      <th style="width: 11%">Unit Cost</th>
      <th style="width: 10%">Total Cost</th>
    </tr>
  </thead>
  <tbody>
    @foreach($iar->items as $i => $item)
    <tr>
      <td class="center">{{ $i + 1 }}</td>
      <td>
        {{ $item->description }}
        @if($item->supplyItem)
          <br><span style="font-size:7pt;color:#666">{{ $item->supplyItem->item_code }}</span>
        @endif
        @if($item->rejection_reason)
          <br><span style="font-size:7pt;color:#c00">Rejection: {{ $item->rejection_reason }}</span>
        @endif
      </td>
      <td class="center">{{ $item->unit }}</td>
      <td class="right">{{ number_format($item->quantity_ordered, 3) }}</td>
      <td class="right">{{ number_format($item->quantity_delivered, 3) }}</td>
      <td class="right">{{ number_format($item->quantity_accepted, 3) }}</td>
      <td class="right">{{ number_format($item->quantity_rejected, 3) }}</td>
      <td class="right">{{ number_format($item->unit_cost, 2) }}</td>
      <td class="right">{{ number_format($item->totalCost(), 2) }}</td>
    </tr>
    @endforeach
    @for($j = count($iar->items); $j < 10; $j++)
    <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
    <tr class="total-row">
      <td colspan="8" class="right">TOTAL:</td>
      <td class="right">{{ number_format($iar->totalAccepted(), 2) }}</td>
    </tr>
  </tbody>
</table>

@if($iar->remarks)
<p style="font-size:8pt;margin-top:6px;"><strong>Remarks:</strong> {{ $iar->remarks }}</p>
@endif

<table class="signatures">
  <tr>
    <td>
      <strong>INSPECTED BY:</strong>
      <div class="sig-line"></div>
      <strong>{{ $iar->inspector?->name ?? '________________________________' }}</strong><br>
      <span class="label">Inspector / Property Inspector</span>
    </td>
    <td style="text-align:center;">
      <strong>ACCEPTED BY:</strong>
      <div class="sig-line"></div>
      <strong>{{ $iar->propertyOfficer?->name ?? '________________________________' }}</strong><br>
      <span class="label">Supply / Property Officer</span>
    </td>
    <td style="text-align:right;">
      <strong>DATE ACCEPTED:</strong>
      <div class="sig-line"></div>
      <strong>{{ $iar->accepted_at?->format('F j, Y') ?? '________________________________' }}</strong>
    </td>
  </tr>
</table>

<div class="footer-note">
  IAR Status: <strong>{{ $iar->statusLabel() }}</strong> &nbsp;|&nbsp;
  Generated: {{ now()->format('F j, Y g:i A') }} &nbsp;|&nbsp;
  This document is computer-generated. | GAM Form No. 5 (COA Circular 2015-010)
</div>

</body>
</html>
