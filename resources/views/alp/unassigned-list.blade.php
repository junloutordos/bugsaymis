<!doctype html><html><head><meta charset="utf-8"><style>
*{box-sizing:border-box}body{font-family:DejaVu Sans,sans-serif;font-size:9pt;color:#000}
.page-body{padding:6px 12.7mm 10px}
h1{text-align:center;font-size:14pt;margin:0 0 3px}
.meta{text-align:center;color:#475569;margin-bottom:16px;font-size:9pt}
table{width:100%;border-collapse:collapse}th,td{border:.6px solid #64748b;padding:5px}th{background:#eef2ff}
.no-data{text-align:center;color:#888;padding:16px}
.footer{margin-top:16px;border-top:1px solid #111;padding-top:4px;font-size:7pt;text-align:right;color:#555}
</style></head><body>
<div class="page-body">
<h1>ALP UNASSIGNED — GRADES 7 TO 10</h1>
<div class="meta">S.Y. {{ $schoolYearName }} | {{ count($students) }} student(s)@if($filterLabel) | Filtered: {{ $filterLabel }}@endif</div>
<table><thead><tr><th>#</th><th>Name</th><th>Grade</th><th>Section</th></tr></thead><tbody>
@forelse($students as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row['name'] }}</td><td>{{ $row['grade_level'] }}</td><td>{{ $row['section'] }}</td></tr>@empty
<tr><td colspan="4" class="no-data">No unassigned scholars found.</td></tr>@endforelse
</tbody></table>
<div class="footer">Printed from Atlas &bull; {{ now()->format('m/d/Y g:i A') }}</div>
</div>
</body></html>
