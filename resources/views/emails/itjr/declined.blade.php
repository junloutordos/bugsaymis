<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>IT Job Request Declined</title>
    <style>
        body{background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px}
        .logo-bar{text-align:center;padding:0 0 18px}
        .logo-img{height:26px;width:auto}
        .logo-sub{font-size:11px;color:#64748b;display:block;margin-top:5px;letter-spacing:.02em}
        .card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 10px 32px rgba(10,42,94,.10);max-width:480px;width:100%;overflow:hidden}
        .card-header{background:linear-gradient(90deg,#dc2626,#ef4444);padding:24px;color:#fff;text-align:center}
        .card-header h1{font-size:20px;margin:0 0 4px}
        .card-header p{margin:0;opacity:.85;font-size:13px}
        .card-body{padding:28px}
        .detail{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:14px}
        .detail:last-child{border-bottom:none}
        .detail .lbl{color:#64748b;font-weight:600}
        .detail .val{color:#0f172a;font-weight:500}
        .badge{display:inline-block;padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:700;background:#fee2e2;color:#991b1b}
        .reason-box{background:#fef2f2;border-left:4px solid #ef4444;border-radius:8px;padding:14px 16px;margin:16px 0;font-size:14px;color:#7f1d1d}
        .footer{padding:16px 28px;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9;text-align:center}
    </style>
</head>
<body>
<div class="logo-bar">
    <img src="/images/atlas-logo-full.png" alt="Atlas" class="logo-img" height="26">
    <span class="logo-sub">Philippine Science High School – Caraga Region Campus</span>
</div>
<div class="card">
    <div class="card-header">
        <h1>Request Declined</h1>
        <p>IT Job Request — Atlas</p>
    </div>
    <div class="card-body">
        <p style="color:#475569;margin:0 0 16px;font-size:14px;">The following IT Job Request has been declined. The requestor has been notified.</p>
        <div class="detail"><span class="lbl">ITJR No.</span><span class="val" style="font-family:monospace;">{{ $jobRequest->itjr_no }}</span></div>
        <div class="detail"><span class="lbl">Title</span><span class="val">{{ $jobRequest->title }}</span></div>
        <div class="detail"><span class="lbl">Status</span><span class="val"><span class="badge">{{ $jobRequest->status }}</span></span></div>
        @if(!empty($reason))
        <div class="reason-box"><strong style="display:block;margin-bottom:4px;font-size:11px;text-transform:uppercase;letter-spacing:.06em;">Reason for Decline</strong>{{ $reason }}</div>
        @endif
    </div>
    <div class="footer">Atlas — You may close this window.</div>
</div>
</body>
</html>
