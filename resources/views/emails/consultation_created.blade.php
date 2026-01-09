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
  <p><strong>Requestor:</strong> {{ $consult->requestor ?? '—' }}</p>
  <p><strong>Unit:</strong> {{ $consult->unit ?? '—' }}</p>
  <p><strong>Reason:</strong> {{ $consult->reason ?? '—' }}</p>
  <p>Please login to the system and schedule the appointment.</p>
  <p>Thanks — BUGSAYMIS</p>
</body>
</html>
