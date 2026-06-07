<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;font-size:8.5pt}
h2{font-size:12pt;text-align:center;margin:0 0 2px}
.subtitle{font-size:7.5pt;text-align:center;margin-bottom:8px}
.form-no{font-size:7pt;text-align:right;margin-bottom:2px}
table.items{width:100%;border-collapse:collapse;margin-top:8px}
table.items th{background:#f0f0f0;font-size:7.5pt;padding:3px 5px;border:1px solid #999;text-align:center}
table.items td{font-size:8pt;padding:2px 5px;border:1px solid #ccc}
.right{text-align:right}.center{text-align:center}
.cat-header{background:#e0e8f0;font-weight:bold;font-size:8pt}
.footer{font-size:7pt;color:#666;margin-top:8px}
</style></head><body>
<div class="form-no">GAM Form No. 21</div>
<h2>REPORT ON THE PHYSICAL COUNT OF INVENTORIES (RPCI)</h2>
<div class="subtitle">Philippine Science High School – Caraga Region Campus &nbsp;|&nbsp; As of: {{ \Carbon\Carbon::parse($as_of)->format('F j, Y') }}</div>
<table class="items">
  <thead>
    <tr>
      <th style="width:10%">Item Code</th>
      <th style="width:30%">Description</th>
      <th style="width:10%">Category</th>
      <th style="width:10%">Account Code</th>
      <th style="width:8%">Unit</th>
      <th style="width:10%">Balance Qty</th>
      <th style="width:10%">Unit Cost</th>
      <th style="width:12%">Total Value</th>
    </tr>
  </thead>
  <tbody>
    @php $totalValue=0; $currentCat=''; @endphp
    @foreach($items as $item)
      @if($item['category_name'] !== $currentCat)
        @php $currentCat=$item['category_name']; @endphp
        <tr class="cat-header"><td colspan="8">{{ $currentCat }}</td></tr>
      @endif
      @php $totalValue += $item['total_value']; @endphp
      <tr>
        <td>{{ $item['item_code'] }}</td>
        <td>{{ $item['description'] }}</td>
        <td>{{ $item['category_name'] }}</td>
        <td class="center">{{ $item['account_code'] ?? '—' }}</td>
        <td class="center">{{ $item['unit'] }}</td>
        <td class="right">{{ number_format($item['balance_quantity'],3) }}</td>
        <td class="right">{{ number_format($item['unit_cost'],2) }}</td>
        <td class="right">{{ number_format($item['total_value'],2) }}</td>
      </tr>
    @endforeach
    <tr style="font-weight:bold;background:#f9f9f9">
      <td colspan="7" class="right">GRAND TOTAL:</td>
      <td class="right">{{ number_format($totalValue,2) }}</td>
    </tr>
  </tbody>
</table>
<div class="footer">Generated: {{ now()->format('F j, Y g:i A') }} | GAM Form No. 21 (COA Circular 2015-010)</div>
</body></html>
