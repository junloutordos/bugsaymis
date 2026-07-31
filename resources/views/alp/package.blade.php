<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,sans-serif;font-size:9pt;color:#111827}h1{text-align:center;font-size:18pt}h2{font-size:11pt}table{width:100%;border-collapse:collapse}th,td{border:.6px solid #64748b;padding:5px}.cover{text-align:center;padding-top:140px}.page-break{page-break-before:always}.header{text-align:center}.meta{font-size:8pt;color:#475569}.label{font-weight:bold}.signature{display:inline-block;width:48%;vertical-align:top;margin-top:20px}.footer{font-size:7pt;border-top:1px solid #111;margin-top:20px}ul{margin-left:18px}
</style></head><body>
<div class="cover"><h1>ALTERNATIVE LEARNING PROGRAM</h1><h2>{{ $cycle->cycle_type === 'reaccreditation' ? 'REACCREDITATION' : 'ACCREDITATION' }} PACKAGE</h2><p>{{ $cycle->program->name }}</p><p>S.Y. {{ $cycle->schoolYear->name }}</p><p>Status: {{ str($cycle->status)->replace('_',' ')->title() }}</p></div>
@foreach($cycle->documents->sortBy('id') as $document)<div class="page-break"></div>@include('alp.document-content', ['document'=>$document,'cycle'=>$cycle])@endforeach
</body></html>
