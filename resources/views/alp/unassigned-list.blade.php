<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9pt}h1{text-align:center;font-size:14pt;margin-bottom:3px}.meta{text-align:center;color:#475569;margin-bottom:18px}table{width:100%;border-collapse:collapse}th,td{border:.6px solid #64748b;padding:5px}th{background:#eef2ff}.footer{margin-top:20px;border-top:1px solid #111;font-size:7pt}
</style></head><body>
<h1>ALP UNASSIGNED — GRADES 7 TO 10</h1>
<div class="meta">S.Y. {{ $schoolYearName }} | {{ count($students) }} student(s) | Generated {{ now()->format('F j, Y') }}</div>
<table><thead><tr><th>#</th><th>Name</th><th>Grade</th><th>Section</th></tr></thead><tbody>
@foreach($students as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row['name'] }}</td><td>{{ $row['grade_level'] }}</td><td>{{ $row['section'] }}</td></tr>@endforeach
</tbody></table>
<div class="footer">Generated {{ now()->format('m/d/Y') }} by BugSayMis CRCMIS</div>
</body></html>
