<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Decline Purchase Request</title>
  <style>
    body { background:#f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:#0f172a; margin:0; padding:24px; }
    .center { max-width:640px; margin:48px auto; text-align:center; }
    .card { background:#fff; padding:28px; border-radius:12px; box-shadow:0 6px 24px rgba(15,23,42,0.08); }
    h1 { margin:0 0 8px; font-size:20px; color:#b91c1c; }
    p { color:#334155; margin:8px 0 16px; }
    .meta { color:#475569; font-size:14px; }
    .btn { display:inline-block; margin-top:12px; padding:10px 16px; background:#3b82f6; color:white; border-radius:8px; text-decoration:none; }
    form { margin-top:12px; }
    textarea { width:100%; min-height:120px; padding:8px; border:1px solid #e2e8f0; border-radius:6px; }
  </style>
</head>
<body>
  <div class="center">
    <div class="card">
      <h1>Decline Purchase Request</h1>
      <p class="meta">Provide a reason for declining the purchase request.</p>
      <form method="POST" action="{{ $postAction }}">
        @csrf
        <textarea name="reason" placeholder="Reason (optional)"></textarea>
        <div style="text-align:center;margin-top:12px;"><button class="btn" type="submit">Submit Decline</button></div>
      </form>
      <p style="text-align:center;margin-top:14px;color:#9ca3af">Request ID: {{ $procurement->id }}</p>
    </div>
  </div>
</body>
</html>
