<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
<style>
body{font-family:Arial,sans-serif;font-size:8pt}
h2{font-size:12pt;text-align:center;margin:0 0 2px}
.subtitle{font-size:8pt;text-align:center;margin-bottom:2px}
.header-table{width:100%;border-collapse:collapse;margin-bottom:6px}
.header-table td{font-size:8pt;padding:2px 4px}
table.grid{width:100%;border-collapse:collapse;margin-top:6px}
table.grid th{background:#e5e7eb;font-size:7.5pt;padding:3px 4px;border:1px solid #999;text-align:center}
table.grid td{font-size:7.5pt;padding:3px 4px;border:1px solid #ccc;vertical-align:top}
tr.band-strategic td.band{background:#dbeafe;font-weight:bold}
tr.band-core td.band{background:#dcfce7;font-weight:bold}
tr.band-support td.band{background:#fef9c3;font-weight:bold}
.right{text-align:right}.center{text-align:center}
table.rollup{width:60%;border-collapse:collapse;margin-top:10px}
table.rollup th,table.rollup td{font-size:7.5pt;padding:3px 6px;border:1px solid #999}
.final-row td{font-weight:bold;background:#f3f4f6}
.signatures{width:100%;margin-top:16px}
.signatures td{width:33%;vertical-align:top;font-size:7.5pt;padding:4px}
.sig-line{border-top:1px solid #333;margin-top:24px;margin-bottom:2px}
</style></head><body>
<h2>INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW (IPCR)</h2>
<div class="subtitle">Philippine Science High School – Caraga Region Campus</div>
<table class="header-table">
  <tr>
    <td width="50%">Name: <strong>{{ $ipcr->user->name }}</strong></td>
    <td width="50%" style="text-align:right">Division: <strong>{{ $ipcr->user->division?->division_name ?? '—' }}</strong></td>
  </tr>
  <tr>
    <td>Rating Period: <strong>{{ $ipcr->ratingPeriod?->label }}</strong></td>
    <td style="text-align:right">Status: <strong>{{ $ipcr->status }}</strong></td>
  </tr>
</table>
<table class="grid">
  <thead>
    <tr>
      <th style="width:20%">Function / Output</th>
      <th style="width:8%">Weight %</th>
      <th style="width:18%">Success Indicator / Target</th>
      <th style="width:18%">Actual Accomplishment</th>
      <th style="width:6%">Q</th><th style="width:6%">E</th><th style="width:6%">T</th><th style="width:6%">Avg</th>
      <th style="width:12%">Remarks</th>
    </tr>
  </thead>
  <tbody>
    @foreach(['strategic' => 'STRATEGIC FUNCTION', 'core' => 'CORE FUNCTION', 'support' => 'SUPPORT FUNCTION'] as $type => $label)
      <tr class="band-{{ $type }}"><td class="band" colspan="9">{{ $label }}</td></tr>
      @foreach($ipcr->rows->where('function_type', $type) as $row)
      <tr>
        <td>{{ $row->templateItem?->output_outcome ?? $row->individual_target }}</td>
        <td class="right">{{ $row->weight_percent }}</td>
        <td>{{ $row->templateItem?->target ?? $row->plan?->success_indicator }}</td>
        <td>{{ $row->accomplishment }}</td>
        <td class="center">{{ $row->sup_quality }}</td>
        <td class="center">{{ $row->sup_efficiency }}</td>
        <td class="center">{{ $row->sup_timeliness }}</td>
        <td class="center">{{ $row->sup_average }}</td>
        <td>{{ $row->remarks }}</td>
      </tr>
      @endforeach
    @endforeach
  </tbody>
</table>
<table class="rollup">
  <tr><th>Function</th><th>Weighted Contribution</th></tr>
  @foreach(['strategic' => 'Strategic', 'core' => 'Core', 'support' => 'Support'] as $type => $label)
    <tr>
      <td>{{ $label }}</td>
      <td class="right">{{ number_format($ipcr->rows->where('function_type', $type)->sum(fn($r) => (float) ($r->sup_average ?? 0) * (float) ($r->weight_percent ?? 0) / 100), 2) }}</td>
    </tr>
  @endforeach
  <tr class="final-row"><td>FINAL AVERAGE RATING</td><td class="right">{{ $ipcr->final_numeric_rating ?? '—' }} ({{ $ipcr->final_adjectival_rating ?? 'Pending' }})</td></tr>
</table>
<table class="signatures">
  <tr>
    <td><strong>EMPLOYEE:</strong><div class="sig-line"></div><strong>{{ $ipcr->user->name }}</strong></td>
    <td><strong>SUPERVISOR:</strong><div class="sig-line"></div></td>
    <td><strong>HEAD OF OFFICE:</strong><div class="sig-line"></div></td>
  </tr>
</table>
</body></html>
