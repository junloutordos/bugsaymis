<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;font-size:9pt}
h2{font-size:13pt;text-align:center;margin:0 0 2px}
.subtitle{font-size:8pt;text-align:center;margin-bottom:8px}
.form-no{font-size:7pt;text-align:right;margin-bottom:2px}
.header-table{width:100%;border-collapse:collapse;margin-bottom:8px}
.header-table td{font-size:8.5pt;padding:2px 4px}
table.items{width:100%;border-collapse:collapse;margin-top:8px}
table.items th{background:#f0f0f0;font-size:8pt;padding:4px 6px;border:1px solid #999;text-align:center}
table.items td{font-size:8pt;padding:3px 6px;border:1px solid #ccc;vertical-align:top}
.right{text-align:right}.center{text-align:center}
.signatures{width:100%;margin-top:16px}
.signatures td{width:50%;vertical-align:top;font-size:8pt;padding:4px}
.sig-line{border-top:1px solid #333;margin-top:28px;margin-bottom:2px}
.footer{font-size:7pt;color:#666;margin-top:8px}
</style></head><body>
<div class="form-no">GAM Form No. 19</div>
<h2>INVENTORY CUSTODIAN SLIP (ICS)</h2>
<div class="subtitle">Philippine Science High School – Caraga Region Campus</div>
<table class="header-table">
  <tr>
    <td width="50%"><strong>ICS No.: {{ $ics->ics_number }}</strong></td>
    <td width="50%" style="text-align:right"><strong>Date: {{ $ics->issue_date?->format('F j, Y') }}</strong></td>
  </tr>
  <tr>
    <td>Division/Office: {{ $ics->division?->division_name ?? '—' }}</td>
    <td style="text-align:right">Issued By: {{ $ics->issuer?->name ?? '—' }}</td>
  </tr>
</table>
<table class="items">
  <thead>
    <tr>
      <th style="width:5%">No.</th>
      <th style="width:12%">Property No.</th>
      <th style="width:33%">Description</th>
      <th style="width:8%">Unit</th>
      <th style="width:10%">Qty</th>
      <th style="width:12%">Unit Cost</th>
      <th style="width:12%">Total Cost</th>
      <th style="width:8%">Est. Life</th>
    </tr>
  </thead>
  <tbody>
    @foreach($ics->items as $i=>$item)
    <tr>
      <td class="center">{{ $i+1 }}</td>
      <td class="center">{{ $item->propertyItem?->property_number ?? '—' }}</td>
      <td>{{ $item->description }}</td>
      <td class="center">{{ $item->unit }}</td>
      <td class="right">{{ number_format($item->quantity,3) }}</td>
      <td class="right">{{ number_format($item->unit_cost,2) }}</td>
      <td class="right">{{ number_format($item->totalCost(),2) }}</td>
      <td class="center">{{ $item->estimated_useful_life?->format('Y-m-d') ?? '—' }}</td>
    </tr>
    @endforeach
    @for($j=count($ics->items);$j<8;$j++)<tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>@endfor
    <tr style="font-weight:bold;background:#f9f9f9">
      <td colspan="6" class="right">TOTAL:</td>
      <td class="right">{{ number_format($ics->totalAmount(),2) }}</td><td></td>
    </tr>
  </tbody>
</table>
<table class="signatures">
  <tr>
    <td><strong>ISSUED BY:</strong>
      <div class="sig-line"></div>
      <strong>{{ $ics->issuer?->name ?? '________________________' }}</strong><br>
      <span style="font-size:7.5pt;color:#555">Supply / Property Officer</span>
    </td>
    <td><strong>RECEIVED BY:</strong>
      <div class="sig-line"></div>
      <strong>{{ $ics->receiver?->name ?? '________________________' }}</strong><br>
      <span style="font-size:7.5pt;color:#555">Accountable Officer / Custodian</span>
    </td>
  </tr>
</table>
<div class="footer">Generated: {{ now()->format('F j, Y g:i A') }} | GAM Form No. 19 (COA Circular 2015-010)</div>
</body></html>
