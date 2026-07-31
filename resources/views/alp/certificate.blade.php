<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;text-align:center;color:#1e293b;border:8px double #4338ca;padding:45px}h1{font-size:30pt;color:#312e81;margin:15px}h2{font-size:17pt}p{font-size:13pt;line-height:1.6}.name{font-size:24pt;font-weight:bold;border-bottom:1px solid #111;display:inline-block;padding:0 35px}.sign{display:inline-block;width:42%;margin:45px 3% 0;font-size:10pt}</style></head><body>
<h2>PHILIPPINE SCIENCE HIGH SCHOOL - CARAGA REGION CAMPUS</h2><h1>CERTIFICATE OF COMPLETION</h1>
<p>This certificate is presented to</p><div class="name">{{ $membership->student->full_name }}</div>
<p>for satisfactorily completing the requirements of</p><h2>{{ $membership->cycle->program->name }}</h2><p>Alternative Learning Program, School Year {{ $membership->cycle->schoolYear->name }}</p>
<div class="sign">{{ $membership->cycle->adviser?->name }}<br><strong>ALP Adviser</strong></div><div class="sign">{{ $membership->cycle->coordinator?->name }}<br><strong>ALP Coordinator</strong></div>
<p style="font-size:8pt;margin-top:35px">Issued {{ now()->format('F j, Y') }} | Certificate record {{ $membership->id }}</p>
</body></html>
