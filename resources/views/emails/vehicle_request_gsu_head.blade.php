<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>GSU Head Vehicle Request Action</title>
  <style>
    body{background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial;color:#0f172a;margin:0;padding:24px}
    .center{max-width:640px;margin:48px auto;text-align:center}
    .card{background:#fff;padding:28px;border-radius:12px;box-shadow:0 6px 24px rgba(15,23,42,0.08)}
    h1{margin:0 0 8px;font-size:20px;color:#0f172a}
    .meta{color:#475569;font-size:14px}
    .btn{display:inline-block;margin:8px 8px 0 0;padding:10px 16px;background:#3b82f6;color:white;border-radius:8px;text-decoration:none}
  </style>
</head>
<body>
  <div class="center">
    <div class="card">
      <h1>GSU Head Action Required</h1>
      <p class="meta">A vehicle request (ID: {{ $request->id }}) has been approved by the division chief and now requires your action.</p>
      <p class="meta">Purpose: {{ $request->purpose }}</p>
      <p class="meta">Destination: {{ $request->destination }}</p>
      <p class="meta">Please log in to the system to review, assign a driver, or decline this request.</p>
    </div>
  </div>
</body>
</html>
