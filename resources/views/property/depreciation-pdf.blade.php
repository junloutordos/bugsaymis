<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;font-size:9pt}
h2{font-size:13pt;text-align:center;margin:0 0 4px}
.subtitle{font-size:8pt;text-align:center;margin-bottom:10px}
.info-table{width:100%;border-collapse:collapse;margin-bottom:10px}
.info-table td{font-size:8.5pt;padding:2px 6px}
.label{font-size:7.5pt;color:#555}
table.sched{width:100%;border-collapse:collapse}
table.sched th{background:#f0f0f0;font-size:8pt;padding:4px 8px;border:1px solid #999;text-align:center}
table.sched td{font-size:8.5pt;padding:3px 8px;border:1px solid #ccc;text-align:right}
table.sched td:first-child{text-align:center}
.footer{font-size:7pt;color:#666;margin-top:10px}
</style></head><body>
<h2>DEPRECIATION LAPSING SCHEDULE</h2>
<div class="subtitle">Philippine Science High School – Caraga Region Campus</div>
<table class="info-table">
  <tr>
    <td width="25%"><span class="label">Property No.:</span><br><strong>{{ $item->property_number }}</strong></td>
    <td width="45%"><span class="label">Description:</span><br><strong>{{ $item->description }}</strong></td>
    <td width="30%"><span class="label">Account Code:</span><br><strong>{{ $item->category?->account_code ?? '—' }}</strong></td>
  </tr>
  <tr>
    <td><span class="label">Acquisition Date:</span><br>{{ $item->acquisition_date?->format('F j, Y') }}</td>
    <td><span class="label">Cost:</span><br>₱{{ number_format($item->totalCost(),2) }}</td>
    <td><span class="label">Residual Value (5%):</span><br>₱{{ number_format($item->residualValue(),2) }}</td>
  </tr>
  <tr>
    <td><span class="label">Useful Life:</span><br>{{ $item->category?->useful_life_years }} years</td>
    <td><span class="label">Depreciable Amount:</span><br>₱{{ number_format($item->depreciableAmount(),2) }}</td>
    <td><span class="label">Monthly Depreciation:</span><br>₱{{ number_format($item->monthlyDepreciation(),2) }}</td>
  </tr>
</table>
<table class="sched">
  <thead>
    <tr>
      <th style="width:12%">Year</th>
      <th style="width:28%">Annual Depreciation</th>
      <th style="width:30%">Accumulated Depreciation</th>
      <th style="width:30%">Book Value</th>
    </tr>
  </thead>
  <tbody>
    @foreach($schedule as $row)
    <tr @if($loop->last) style="font-weight:bold" @endif>
      <td style="text-align:center">{{ $row['year'] }}</td>
      <td>₱{{ number_format($row['annual_depreciation'],2) }}</td>
      <td>₱{{ number_format($row['accumulated'],2) }}</td>
      <td>₱{{ number_format($row['book_value'],2) }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
<div class="footer">Generated: {{ now()->format('F j, Y g:i A') }} | Straight-Line Method | 5% Residual Value | COA Circular 2015-010</div>
</body></html>
