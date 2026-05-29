<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OCD Decline — IT Job Request</title>
    <style>
        body{background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:16px}
        .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(15,23,42,.1);max-width:520px;width:100%;overflow:hidden}
        .card-header{background:linear-gradient(90deg,#dc2626,#ef4444);padding:24px;color:#fff}
        .card-header h1{font-size:18px;margin:0 0 4px}
        .card-header p{margin:0;opacity:.85;font-size:13px}
        .card-body{padding:28px}
        .detail{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #f1f5f9;font-size:14px;margin-bottom:4px}
        .detail .lbl{color:#64748b;font-weight:600}
        .detail .val{color:#0f172a}
        label{display:block;font-size:13px;font-weight:600;color:#374151;margin:16px 0 6px}
        textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d1d5db;border-radius:8px;font-size:14px;font-family:inherit;resize:vertical;min-height:100px;outline:none}
        textarea:focus{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.15)}
        button{width:100%;margin-top:12px;background:#dc2626;color:#fff;border:none;padding:12px;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;font-family:inherit}
        button:hover{background:#b91c1c}
        .footer{padding:14px 28px;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9;text-align:center}
    </style>
</head>
<body>
<div class="card">
    <div class="card-header">
        <h1>Decline IT Job Request</h1>
        <p>PSHS-CRC MIS — OCD Action</p>
    </div>
    <div class="card-body">
        <p style="color:#475569;font-size:14px;margin:0 0 16px;">You are declining the following IT Job Request as OCD. Please provide a reason so the requestor and Division Chief are informed.</p>
        <div class="detail"><span class="lbl">ITJR No.</span><span class="val" style="font-family:monospace;">{{ $jobRequest->itjr_no }}</span></div>
        <div class="detail"><span class="lbl">Title</span><span class="val">{{ $jobRequest->title }}</span></div>
        <div class="detail"><span class="lbl">Submitted By</span><span class="val">{{ $jobRequest->user?->name ?? '—' }}</span></div>
        <div class="detail"><span class="lbl">DC Approved</span><span class="val">{{ $jobRequest->dc_approval_date?->format('F j, Y') ?? '—' }}</span></div>
        <form method="POST" action="{{ $postAction }}">
            @csrf
            <label for="reason">Reason for Decline <span style="color:#ef4444">*</span></label>
            <textarea name="reason" id="reason" rows="4" required placeholder="Explain why this request is being declined…"></textarea>
            <button type="submit">Submit Decline</button>
        </form>
    </div>
    <div class="footer">PSHS-CRC MIS</div>
</div>
</body>
</html>
