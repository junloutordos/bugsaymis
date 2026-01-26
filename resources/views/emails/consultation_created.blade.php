<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>New Consultation Request</title>
</head>
<body>
  <p>Hello Nurse,</p>
  <p>A new consultation request was submitted and needs scheduling.</p>
  <p><strong>Request ID:</strong> {{ $consult->id }}</p>
  <p><strong>Requestor:</strong> {{ $requestor->name ?? ($consult->requestor ?? '—') }}</p>
  <p><strong>Sex:</strong> {{ $requestor->sex ?? '—' }}</p>
  <p><strong>Date Scheduled:</strong> {{ $dateScheduled ?? '—' }}</p>
  @if(!empty($requestor->grade_level) || !empty($requestor->section))
    <p><strong>Grade Level:</strong> {{ $requestor->grade_level ?? '—' }}</p>
    <p><strong>Section:</strong> {{ $requestor->section ?? '—' }}</p>
  @else
    <p><strong>Unit/Office:</strong> {{ $requestor->office ?? ($consult->unit ?? '—') }}</p>
  @endif
  <p><strong>Reason:</strong> {{ $consult->reason ?? '—' }}</p>
  <p>Please login to the system and schedule the appointment.</p>
  <p>Thanks — BUGSAYMIS</p>
</body>
</html>
