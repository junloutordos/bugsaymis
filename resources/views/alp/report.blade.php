<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:9pt}h1{text-align:center;font-size:14pt}table{width:100%;border-collapse:collapse}th,td{border:.6px solid #64748b;padding:5px}th{background:#eef2ff}.meta{text-align:center;color:#475569}</style></head><body>
<h1>ALTERNATIVE LEARNING PROGRAM<br>{{ strtoupper(str_replace('_',' ', $report->report_type)) }} REPORT</h1>
<div class="meta">{{ $cycle->program->name }} | {{ $report->period }} | S.Y. {{ $cycle->schoolYear->name }}</div>
@foreach(($report->data ?? []) as $key => $value)
    <h3>{{ str($key)->replace('_',' ')->title() }}</h3>
    @if(is_array($value))
        <table><tbody>@foreach($value as $rowKey => $row)<tr><th>{{ is_string($rowKey) ? str($rowKey)->replace('_',' ')->title() : $loop->iteration }}</th><td>{{ is_array($row) ? json_encode($row, JSON_UNESCAPED_UNICODE) : $row }}</td></tr>@endforeach</tbody></table>
    @else<p>{{ $value }}</p>@endif
@endforeach
<p style="margin-top:25px"><strong>Prepared by:</strong> {{ $report->preparer?->name }}</p><p><strong>Reviewed by:</strong> {{ $report->reviewer?->name }}</p>
@if($report->form_code)<p style="margin-top:20px;border-top:1px solid #111;font-size:7pt">{{ $report->form_code }}-Ver{{ str_pad($report->version_no, 2, '0', STR_PAD_LEFT) }}-Rev{{ $report->revision_no }} | Generated {{ now()->format('m/d/Y') }}</p>@endif
</body></html>
