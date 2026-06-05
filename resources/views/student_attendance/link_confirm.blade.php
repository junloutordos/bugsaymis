<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MyPisay — Parent Link Request</title>
<style>
*{box-sizing:border-box}
body{background:#f0f4f8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;color:#334155;margin:0;padding:20px 12px;min-height:100vh;display:flex;align-items:center;justify-content:center}
.card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(15,23,42,.1);max-width:440px;width:100%;overflow:hidden}
.header{background:linear-gradient(135deg,#1a3557,#1a4480);padding:28px 28px 24px;text-align:center}
.header img{width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.15);padding:8px;margin-bottom:12px}
.header h1{color:#fff;font-size:18px;font-weight:700;margin:0 0 4px}
.header p{color:rgba(255,255,255,.7);font-size:13px;margin:0}
.body{padding:28px}
.icon{width:64px;height:64px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px}
.icon-pending{background:#dbeafe}
.icon-confirmed{background:#d1fae5}
.icon-denied{background:#fee2e2}
.icon-invalid{background:#f1f5f9}
h2{font-size:18px;font-weight:700;color:#0f172a;text-align:center;margin:0 0 8px}
p{font-size:14px;color:#475569;line-height:1.6;margin:0 0 12px;text-align:center}
.detail{background:#f8fafc;border-radius:8px;padding:14px 16px;margin:16px 0}
.detail-row{display:flex;justify-content:space-between;font-size:13px;padding:4px 0}
.detail-row .lbl{color:#64748b;font-weight:600}
.detail-row .val{color:#0f172a;font-weight:500}
.actions{display:flex;gap:10px;margin-top:20px}
.btn{flex:1;padding:13px;border-radius:10px;border:none;cursor:pointer;font-size:14px;font-weight:600;text-decoration:none;text-align:center;display:block}
.btn-confirm{background:#059669;color:#fff}
.btn-deny{background:#fff;color:#dc2626;border:1.5px solid #fca5a5}
.footer{padding:14px 28px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:12px;color:#94a3b8;text-align:center}
</style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>MyPisay</h1>
        <p>PSHS – Caraga Region Campus</p>
    </div>
    <div class="body">

    @if($state === 'pending')
        <div class="icon icon-pending">👨‍👩‍👧</div>
        <h2>Parent Link Request</h2>
        <p>Someone is requesting to be linked to your MyPisay account to monitor your gate attendance.</p>
        <div class="detail">
            <div class="detail-row"><span class="lbl">Name</span><span class="val">{{ $parentName }}</span></div>
            <div class="detail-row"><span class="lbl">Relationship</span><span class="val">{{ $relationship }}</span></div>
        </div>
        <p style="font-size:13px;color:#64748b;">If approved, they will receive notifications when you enter or leave campus.</p>
        <div class="actions">
            <form method="POST" action="/student-attendance/link/{{ $token }}/confirm" style="flex:1">
                @csrf
                <button type="submit" class="btn btn-confirm">✓ Yes, Allow</button>
            </form>
            <form method="POST" action="/student-attendance/link/{{ $token }}/deny" style="flex:1">
                @csrf
                <button type="submit" class="btn btn-deny">✕ Deny</button>
            </form>
        </div>

    @elseif($state === 'confirmed')
        <div class="icon icon-confirmed">✅</div>
        <h2>Request Approved</h2>
        <p><strong>{{ $parentName }}</strong> has been linked to your account and will now receive gate attendance notifications.</p>
        <p style="font-size:13px;color:#64748b;">You can close this page.</p>

    @elseif($state === 'denied')
        <div class="icon icon-denied">🚫</div>
        <h2>Request Denied</h2>
        <p>You have denied the link request. The requestor has not been notified.</p>
        <p style="font-size:13px;color:#64748b;">You can close this page.</p>

    @else
        <div class="icon icon-invalid">⏱</div>
        <h2>Link Expired or Invalid</h2>
        <p>This link has expired or has already been used. If you need to re-link, ask the parent to send a new request through the MyPisay app.</p>
    @endif

    </div>
    <div class="footer">Philippine Science High School – Caraga Region Campus &nbsp;·&nbsp; MyPisay</div>
</div>
</body>
</html>
