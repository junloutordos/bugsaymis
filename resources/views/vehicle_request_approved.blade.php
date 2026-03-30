<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Request Approved</title>
  <style>
    body { background:#f3f4f6; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; color:#0f172a; margin:0; padding:24px; }
    .center { max-width:640px; margin:48px auto; text-align:center; }
    .card { background:#fff; padding:28px; border-radius:12px; box-shadow:0 6px 24px rgba(15,23,42,0.08); }
    h1 { margin:0 0 8px; font-size:20px; color:#065f46; }
    p { color:#334155; margin:8px 0 16px; }
    .meta { color:#475569; font-size:14px; }
    .btn { display:inline-block; margin-top:12px; padding:10px 16px; background:#3b82f6; color:white; border-radius:8px; text-decoration:none; }
  </style>
</head>
<body>
  <div class="center">
    <div class="card" id="approvedCard">
      @php $already = $already ?? false; @endphp
      <h1>{{ $already ? 'Request Already Approved' : 'Vehicle Request Approved' }}</h1>
      <p class="meta">{{ $already ? 'This request was already approved.' : 'Thank you — the request has been approved successfully.' }}</p>
      <p class="meta">Request ID: {{ $vehicleRequest->id }}</p>
      <p><a class="btn" id="returnBtn" href="{{ url('/') }}">Return to Application</a></p>
    </div>
  </div>
  <script>
    (function(){
      const id = {{ $vehicleRequest->id }};
      // Notify opener (if any) so it can refresh its state
      try {
        if (window.opener && !window.opener.closed) {
          window.opener.postMessage({ type: 'vehicleRequestApproved', id: id, already: {{ $already ? 'true' : 'false' }} }, '*');
        }
      } catch (e){ /* ignore */ }

      // If the request was already approved, show a popup alert for clarity
      @if(!empty($already))
        try { alert('This request has already been approved.'); } catch(e){}
      @endif

      // Attempt to close the window after a short delay
      const tryClose = () => {
        try {
          window.close();
        } catch (e) { /* ignore */ }
      }

      // Some browsers disallow closing a tab not opened by script.
      // If close fails, redirect to app after 4s.
      setTimeout(() => {
        tryClose();
        setTimeout(() => { window.location.href = '{{ url('/') }}'; }, 1200);
      }, 2200);

      // Also handle manual return button: notify opener then close
      document.getElementById('returnBtn')?.addEventListener('click', function(){
        try { if (window.opener && !window.opener.closed) window.opener.postMessage({ type: 'vehicleRequestApproved', id: id }, '*'); } catch(e){}
        setTimeout(tryClose, 200);
      });
    })();
  </script>
</body>
</html>
