<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9pt}h1{text-align:center;font-size:14pt;margin-bottom:3px}.meta{text-align:center;color:#475569;margin-bottom:18px}table{width:100%;border-collapse:collapse}th,td{border:.6px solid #64748b;padding:5px}th{background:#eef2ff}.footer{margin-top:20px;border-top:1px solid #111;font-size:7pt}
</style></head><body>
<h1>ALTERNATIVE LEARNING PROGRAM ATTENDANCE FORM</h1>
<div class="meta">{{ $session->cycle->program->name }} | {{ $session->topic ?: 'Regular Session' }} | {{ $session->session_date->format('F j, Y') }} | S.Y. {{ $session->cycle->schoolYear->name }}</div>
<table><thead><tr><th>#</th><th>Scholar</th><th>Grade & Section</th><th>Status</th><th>Remarks</th></tr></thead><tbody>
@foreach($session->attendance->sortBy(fn($row) => $row->membership->student->lastname) as $row)<tr><td>{{ $loop->iteration }}</td><td>{{ $row->membership->student->full_name }}</td><td>{{ $row->membership->enrollment?->grade_level }} - {{ $row->membership->enrollment?->section?->sectionname }}</td><td>{{ ucfirst($row->status) }}</td><td>{{ $row->remarks }}</td></tr>@endforeach
</tbody></table>
<p style="margin-top:24px"><strong>Certified correct by:</strong><br><br>{{ $session->cycle->adviser?->name }}<br>ALP Adviser</p>
<div class="footer">PSHS-00-F-DSA-33-Ver02-Rev0 | Generated {{ now()->format('m/d/Y') }} | Controlled when verified in BugSayMis</div>
</body></html>
