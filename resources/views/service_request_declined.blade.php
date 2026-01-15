<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Request Declined</title>
  <style>
    body{background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial;color:#0f172a;margin:0;padding:24px}
    .center{max-width:640px;margin:48px auto;text-align:center}
    .card{background:#fff;padding:28px;border-radius:12px;box-shadow:0 6px 24px rgba(15,23,42,0.08)}
    h1{margin:0 0 8px;font-size:20px;color:#9f1239}
    p{color:#334155;margin:8px 0 16px}
    .meta{color:#475569;font-size:14px}
    .btn{display:inline-block;margin-top:12px;padding:10px 16px;background:#3b82f6;color:white;border-radius:8px;text-decoration:none}
  </style>
</head>
<body>
  <div class="center">
    <div class="card">
      <h1>Service Request Declined</h1>
      <p class="meta">The request has been declined and the reason was recorded.</p>
      <p class="meta">Request ID: {{ $serviceRequest->id }}</p>
      <p class="meta">Reason: {{ $reason }}</p>
      <p><a class="btn" href="{{ url('/') }}">Return to Application</a></p>
    </div>
  </div>
  <script>
    (function(){
      const id = {{ $serviceRequest->id }};
      const reason = @json($reason);
      try {
        if (window.opener && !window.opener.closed) {
          window.opener.postMessage({ type: 'serviceRequestDeclined', id: id, reason: reason }, '*');
        }
      } catch (e) { }

      const tryClose = () => { try { window.close(); } catch (e) { } }
      setTimeout(() => { tryClose(); setTimeout(() => { window.location.href = '{{ url('/') }}'; }, 1200); }, 2200);
    })();
  </script>
</body>
</html>
