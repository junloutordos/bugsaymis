<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Consultation Scheduled</title>
</head>
<body>
  <p>Hello {{ $consult->requestor ?? 'Requestor' }},</p>
  <p>Your consultation has been scheduled.</p>
  <p><strong>Date & Time:</strong> {{ optional($consult->scheduled_at)->toDayDateTimeString() ?? '—' }}</p>
  <p><strong>Notes:</strong> {{ $consult->notes ?? '—' }}</p>
  <p>Please come to the clinic at the scheduled time.</p>
  <p>Thanks — BUGSAYMIS</p>
</body>
</html>
