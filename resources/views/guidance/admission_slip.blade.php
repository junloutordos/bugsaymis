<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Guidance Admission Slip</title>
  <style>
    body { font-family: Arial, Helvetica, sans-serif; margin: 14mm; color: #111; font-size: 14px; line-height: 1.35; }
    .copy-label { text-align: right; font-weight: 700; margin-bottom: 6px; }
    .center { text-align: center; }
    .slip-title { text-align: center; font-size: 16px; font-weight: 700; margin: 14px 0 16px; letter-spacing: 0.4px; }
    .line-text { margin: 10px 0; }
    .signature { margin-top: 18px; }
    .line { margin-top: 12px; }
    .footer-code { margin-top: 12px; font-weight: 700; }
    .separator { margin: 22px 0; }
    .separator .dash { border-top: 2px dashed #111; height: 0; margin: 8px 0; }
    .campus { margin-top: 2px; }
    .teacher-sign-note { padding-left: 90px; }
    @media print {
      body { margin: 10mm; }
    }
  </style>
</head>
<body>
  @php
    $visitDate = $visitAt ? \Carbon\Carbon::parse($visitAt)->format('F j, Y') : '__________________';
    $visitTime = $visitAt ? \Carbon\Carbon::parse($visitAt)->format('g:i A') : '__________';
    $upToTime = !empty($consult?->updated_at) ? \Carbon\Carbon::parse($consult->updated_at)->format('g:i A') : '__________';
    $student = !empty($studentName) ? $studentName : '__________________________________';
    $section = !empty($gradeSection) ? $gradeSection : '________________';
    $copyLabels = ['GUIDANCE COPY', 'TEACHER’S COPY'];
  @endphp

  @foreach ($copyLabels as $index => $copyLabel)
    <div class="copy-label">{{ $copyLabel }}</div>
    <div class="center">
      <div class="slip-title"><strong>PHILIPPINE SCIENCE HICH SCHOOL SYSTEM <br>Caraga Region Campus in Butuan City</strong></div>
      <div class="campus"></div>
    </div>
    <div class="slip-title">GUIDANCE ADMISSION SLIP</div>

    <div class="line-text"><strong>Name of Student:</strong><u> {{ $student }} </u>&nbsp;&nbsp;<strong>Grade &amp; Section:</strong> <u>{{ $section }}</u></div>
<br>
    <div class="line-text">Dear <strong>{{ $teacherName ?: '____________________________' }},</strong></div>
<br>
    <div class="line-text">
      The student had visited the Guidance office last {{ $visitDate }} at {{ $visitTime }} up to {{ $upToTime }}.
    </div>

    <div class="line-text">Kindly excuse the student in your class/es during the said date and time. Thank you for your kind consideration.</div>

    <div class="signature">
      <div class="line"><strong><u>{{ $counselorName ? mb_strtoupper($counselorName) : '______________________________________________' }}</u></strong></div>
      <div>Guidance Counselor / Life Coaches/ Wellness Facilitator</div>
    </div>

    <div class="signature">
      <div><strong>Received by:</strong> <strong><u> {{ mb_strtoupper($teacherName) ?: '____________________________' }},</u></strong></div>
      <div class="teacher-sign-note"> Name and Signature of Subject Teacher/ Adviser</div>
    </div>
<br>
    <div class="footer-code">PSHS-00-F-GCU-05-Ver02-Rev0-02/01/20</div>

    @if ($index === 0)
      <div class="separator" aria-hidden="true">
        <div class="dash"></div>
        <div class="dash"></div>
      </div>
    @endif
  @endforeach

  <script>
    window.addEventListener('load', function () {
      window.print();
    });
  </script>
</body>
</html>
