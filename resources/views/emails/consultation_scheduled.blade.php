<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Consultation Scheduled</title>
</head>
<body>
  <p>Hello {{ $consult->requestor ?? ($requestor->name ?? 'Requestor') }},</p>
  <p>Your consultation has been scheduled for <strong>{{ $dateScheduled ?? '—' }}</strong>.</p>
  <p>Please come to the clinic at the scheduled time.</p>
  <p>Thanks — BUGSAYMIS</p>
</body>
</html>
