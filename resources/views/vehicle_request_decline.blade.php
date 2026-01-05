<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Decline Vehicle Request</title>
  <style>
    body{background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial;color:#0f172a;margin:0;padding:24px}
    .center{max-width:720px;margin:48px auto;text-align:left}
    .card{background:#fff;padding:20px;border-radius:10px;box-shadow:0 6px 20px rgba(2,6,23,0.06)}
    label{display:block;font-weight:600;margin-bottom:6px}
    textarea{width:100%;min-height:140px;padding:10px;border-radius:6px;border:1px solid #e2e8f0}
    .actions{margin-top:12px;text-align:right}
    .btn{display:inline-block;padding:10px 14px;border-radius:8px;color:#fff;text-decoration:none}
    .btn-decline{background:#ef4444}
    .btn-cancel{background:#94a3b8;margin-right:8px}
  </style>
</head>
<body>
  <div class="center">
    <div class="card">
      <h2>Decline Vehicle Request #{{ $vehicleRequest->id }}</h2>
      <p>Please provide a reason for declining this request. The requester will be notified.</p>

      <form method="post" action="{{ $postAction }}">
        @csrf
        <div>
          <label for="reason">Reason</label>
          <textarea id="reason" name="reason" required>{{ old('reason') }}</textarea>
        </div>
        <div class="actions">
          <a class="btn btn-cancel" href="javascript:window.close()">Close</a>
          <button type="submit" class="btn btn-decline">Submit Decline</button>
        </div>
      </form>
    </div>
  </div>
</body>
</html>
