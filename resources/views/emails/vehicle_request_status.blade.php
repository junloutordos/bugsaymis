<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Vehicle Request Status</title>
  <style>
    body{background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial;color:#0f172a;margin:0;padding:24px}
    .center{max-width:640px;margin:48px auto;text-align:center}
    .card{background:#fff;padding:28px;border-radius:12px;box-shadow:0 6px 24px rgba(15,23,42,0.08)}
    h1{margin:0 0 8px;font-size:20px;color:#0f172a}
    .status-approved{background:#bbf7d0;color:#166534;padding:8px 0;border-radius:8px;font-weight:600;}
    .status-declined{background:#fecaca;color:#991b1b;padding:8px 0;border-radius:8px;font-weight:600;}
    .meta{color:#475569;font-size:14px}
    .btn{display:inline-block;margin-top:12px;padding:10px 16px;background:#3b82f6;color:white;border-radius:8px;text-decoration:none}
  </style>
</head>
<body>
  <div class="center">
    <div class="card">
      <h1>Vehicle Request {{ $status }}</h1>
      <div class="status-{{ strtolower($status) }}">{{ $status }}</div>
      <p class="meta">Request ID: {{ $request->id }}</p>
      <p class="meta">Purpose: {{ $request->purpose }}</p>
      <p class="meta">Destination: {{ $request->destination }}</p>
      @if($status === 'Declined')
        <p class="meta"><strong>Reason:</strong> {{ $reason }}</p>
      @endif
      <p><a class="btn" href="{{ url('/') }}">Return to Application</a></p>
    </div>
  </div>
</body>
</html>
