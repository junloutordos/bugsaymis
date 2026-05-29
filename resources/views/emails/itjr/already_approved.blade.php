<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Already Processed</title>
    <style>
        body{background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px}
        .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(15,23,42,.1);max-width:480px;width:100%;overflow:hidden}
        .card-header{background:linear-gradient(90deg,#d97706,#f59e0b);padding:24px;color:#fff;text-align:center}
        .card-header h1{font-size:20px;margin:0 0 4px}
        .card-header p{margin:0;opacity:.85;font-size:13px}
        .card-body{padding:28px}
        .detail{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:14px}
        .detail:last-child{border-bottom:none}
        .detail .lbl{color:#64748b;font-weight:600}
        .detail .val{color:#0f172a;font-weight:500}
        .badge{display:inline-block;padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:700;background:#fef3c7;color:#92400e}
        .footer{padding:16px 28px;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9;text-align:center}
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1>Already Processed</h1>
        <p>IT Job Request — PSHS-CRC MIS</p>
    </div>
    <div class="card-body">
        <p style="color:#475569;margin:0 0 16px;font-size:14px;">This IT Job Request has already been processed. No further action is needed from you.</p>
        <div class="detail"><span class="lbl">ITJR No.</span><span class="val" style="font-family:monospace;">{{ $jobRequest->itjr_no }}</span></div>
        <div class="detail"><span class="lbl">Current Status</span><span class="val"><span class="badge">{{ $jobRequest->status }}</span></span></div>
    </div>
    <div class="footer">PSHS-CRC MIS — You may close this window.</div>
</div>
</body>
</html>
