<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Decline IT Job Request</title>
    <style>
        body{background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px}
        .logo-bar{text-align:center;padding:0 0 18px}
        .logo-img{height:26px;width:auto}
        .logo-sub{font-size:11px;color:#64748b;display:block;margin-top:5px;letter-spacing:.02em}
        .card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 10px 32px rgba(10,42,94,.10);max-width:520px;width:100%;overflow:hidden}
        .card-header{background:linear-gradient(90deg,#dc2626,#ef4444);padding:24px;color:#fff}
        .card-header h1{font-size:18px;margin:0 0 4px}
        .card-header p{margin:0;opacity:.85;font-size:13px}
        .card-body{padding:28px}
        .detail{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:14px;margin-bottom:4px}
        .detail .lbl{color:#64748b;font-weight:600}
        .detail .val{color:#0f172a}
        label{display:block;font-size:13px;font-weight:600;color:#374151;margin:16px 0 6px}
        textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:14px;font-family:inherit;resize:vertical;min-height:100px;outline:none}
        textarea:focus{border-color:#019FE6;box-shadow:0 0 0 3px rgba(1,159,230,.25)}
        button{width:100%;margin-top:12px;background:#dc2626;color:#fff;border:none;padding:12px;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit}
        button:hover{background:#b91c1c}
        .footer{padding:14px 28px;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9;text-align:center}
    </style>
</head>
<body>
<div class="logo-bar">
    <img src="/images/atlas-logo-full.png" alt="Atlas" class="logo-img" height="26">
    <span class="logo-sub">Philippine Science High School – Caraga Region Campus</span>
</div>
<div class="card">
    <div class="card-header">
        <h1>Decline IT Job Request</h1>
        <p>Atlas — Division Chief Action</p>
    </div>
    <div class="card-body">
        <p style="color:#475569;font-size:14px;margin:0 0 16px;">You are declining the following IT Job Request. Please provide a reason so the requestor is informed.</p>
        <div class="detail"><span class="lbl">ITJR No.</span><span class="val" style="font-family:monospace;">{{ $jobRequest->itjr_no }}</span></div>
        <div class="detail"><span class="lbl">Title</span><span class="val">{{ $jobRequest->title }}</span></div>
        <div class="detail"><span class="lbl">Submitted By</span><span class="val">{{ $jobRequest->user?->name ?? '—' }}</span></div>
        <form method="POST" action="{{ $postAction }}">
            @csrf
            <label for="reason">Reason for Decline <span style="color:#ef4444">*</span></label>
            <textarea name="reason" id="reason" rows="4" required placeholder="Explain why this request is being declined…"></textarea>
            <button type="submit">Submit Decline</button>
        </form>
    </div>
    <div class="footer">Atlas</div>
</div>
</body>
</html>
