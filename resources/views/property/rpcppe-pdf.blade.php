<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;font-size:8pt}
h2{font-size:12pt;text-align:center;margin:0 0 2px}
.subtitle{font-size:7.5pt;text-align:center;margin-bottom:8px}
.form-no{font-size:7pt;text-align:right;margin-bottom:2px}
table.items{width:100%;border-collapse:collapse;margin-top:8px}
table.items th{background:#f0f0f0;font-size:7pt;padding:3px 4px;border:1px solid #999;text-align:center}
table.items td{font-size:7.5pt;padding:2px 4px;border:1px solid #ccc;vertical-align:top}
.right{text-align:right}.center{text-align:center}
.cat-header{background:#e0e8f0;font-weight:bold;font-size:7.5pt}
.footer{font-size:7pt;color:#666;margin-top:8px}
</style></head><body>
<div class="form-no">GAM Form No. 22</div>
<h2>REPORT ON THE PHYSICAL COUNT OF PROPERTY, PLANT AND EQUIPMENT (RPCPPE)</h2>
<div class="subtitle">Philippine Science High School – Caraga Region Campus &nbsp;|&nbsp; As of: {{ \Carbon\Carbon::parse($as_of)->format('F j, Y') }}</div>
<table class="items">
  <thead>
    <tr>
      <th>Prop. No.</th>
      <th>Description</th>
      <th>Brand/Model</th>
      <th>Serial No.</th>
      <th>Location</th>
      <th>Acct. Code</th>
      <th>Acq. Date</th>
      <th>Qty</th>
      <th>Unit Cost</th>
      <th>Total Cost</th>
      <th>Accum. Dep.</th>
      <th>Book Value</th>
      <th>Accountable Officer</th>
    </tr>
  </thead>
  <tbody>
    @php $totalCost=0;$totalDep=0;$totalBv=0;$currentCat=''; @endphp
    @foreach($items as $item)
      @if($item['category_name'] !== $currentCat)
        @php $currentCat=$item['category_name']; @endphp
        <tr class="cat-header"><td colspan="13">{{ $currentCat }} ({{ $item['account_code'] ?? '—' }})</td></tr>
      @endif
      @php $totalCost+=$item['total_cost'];$totalDep+=$item['accumulated_dep'];$totalBv+=$item['book_value']; @endphp
      <tr>
        <td>{{ $item['property_number'] }}</td>
        <td>{{ $item['description'] }}</td>
        <td>{{ trim(($item['brand']??'').' '.($item['model']??'')) ?: '—' }}</td>
        <td>{{ $item['serial_number'] ?? '—' }}</td>
        <td>{{ $item['location'] ?? '—' }}</td>
        <td class="center">{{ $item['account_code'] ?? '—' }}</td>
        <td class="center">{{ $item['acquisition_date'] ? \Carbon\Carbon::parse($item['acquisition_date'])->format('m/d/Y') : '—' }}</td>
        <td class="right">{{ number_format($item['quantity'],3) }}</td>
        <td class="right">{{ number_format($item['unit_cost'],2) }}</td>
        <td class="right">{{ number_format($item['total_cost'],2) }}</td>
        <td class="right">{{ number_format($item['accumulated_dep'],2) }}</td>
        <td class="right">{{ number_format($item['book_value'],2) }}</td>
        <td>{{ $item['officer_name'] ?? '—' }}</td>
      </tr>
    @endforeach
    <tr style="font-weight:bold;background:#f9f9f9">
      <td colspan="9" class="right">TOTALS:</td>
      <td class="right">{{ number_format($totalCost,2) }}</td>
      <td class="right">{{ number_format($totalDep,2) }}</td>
      <td class="right">{{ number_format($totalBv,2) }}</td>
      <td></td>
    </tr>
  </tbody>
</table>
<div class="footer">Generated: {{ now()->format('F j, Y g:i A') }} | GAM Form No. 22 (COA Circular 2015-010)</div>
</body></html>
