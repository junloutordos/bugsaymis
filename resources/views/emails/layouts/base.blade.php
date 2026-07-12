<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('email-title', 'Atlas Notification')</title>
    <style>
        *{box-sizing:border-box}
        body{background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#334155;margin:0;padding:24px 12px}
        .wrap{max-width:600px;margin:0 auto}
        .logo-bar{text-align:center;padding:8px 0 18px}
        .logo-img{height:28px;width:auto;display:inline-block}
        .logo-sub{font-size:11px;color:#64748b;display:block;margin-top:6px;letter-spacing:.02em}
        .card{background:#fff;border-radius:16px;border:1px solid #e2e8f0;box-shadow:0 10px 32px rgba(10,42,94,.10);overflow:hidden}
        .card-header{padding:26px 28px;color:#fff;background-color:#0867DB;background-image:linear-gradient(135deg,#0A2A5E,#0867DB)}
        .card-header h1{font-size:19px;font-weight:700;margin:0 0 4px;line-height:1.3;letter-spacing:-.01em}
        .card-header p{margin:0;font-size:13px;opacity:.85}
        .card-body{padding:26px 28px}
        .greeting{font-size:15px;font-weight:600;color:#0f172a;margin:0 0 12px}
        .lead{font-size:14px;color:#475569;margin:0 0 16px;line-height:1.65}
        .details{width:100%;border-collapse:collapse;margin:16px 0}
        .details tr{border-bottom:1px solid #f1f5f9}
        .details tr:last-child{border-bottom:none}
        .details td{padding:9px 4px;vertical-align:top;font-size:14px}
        .lbl{color:#64748b;width:38%;font-weight:600;padding-right:8px}
        .val{color:#0f172a}
        .badge{display:inline-block;padding:3px 10px;border-radius:9999px;font-size:12px;font-weight:600}
        .badge-purple{background:#ede9fe;color:#6d28d9}
        .badge-green{background:#d1fae5;color:#065f46}
        .badge-amber{background:#fef3c7;color:#92400e}
        .badge-red{background:#fee2e2;color:#991b1b}
        .badge-blue{background:#DCEEFE;color:#0552B0}
        .badge-cyan{background:#cffafe;color:#155e75}
        .badge-slate{background:#f1f5f9;color:#475569}
        .priority-urgent{background:#fce7f3;color:#9d174d;font-weight:700}
        .priority-high{background:#fee2e2;color:#991b1b}
        .priority-normal{background:#e0f2fe;color:#075985}
        .priority-low{background:#f1f5f9;color:#475569}
        .callout{border-radius:8px;padding:14px 16px;margin:18px 0 4px;font-size:14px;line-height:1.55}
        .callout-blue{background:#EFF6FF;border-left:4px solid #0867DB;color:#0A2A5E}
        .callout-green{background:#f0fdf4;border-left:4px solid #22c55e;color:#14532d}
        .callout-amber{background:#fffbeb;border-left:4px solid #f59e0b;color:#78350f}
        .callout-red{background:#fef2f2;border-left:4px solid #ef4444;color:#7f1d1d}
        .callout-title{font-weight:700;font-size:11px;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px}
        .rating-grid{width:100%;border-collapse:collapse;margin:14px 0;font-size:13px}
        .rating-grid th{background:#f8fafc;color:#64748b;font-weight:600;padding:8px 6px;text-align:center;border:1px solid #e2e8f0;font-size:11px;text-transform:uppercase}
        .rating-grid td{padding:9px 6px;text-align:center;border:1px solid #e2e8f0;color:#334155}
        .rating-grid .plan-col{text-align:left;color:#475569;font-size:12px}
        .rating-grid .avg-val{font-weight:700;color:#1e293b}
        .rating-grid .overall-row{background:#f0fdf4;font-weight:700}
        .card-actions{padding:6px 28px 24px;text-align:center}
        .btn{display:inline-block;padding:12px 28px;border-radius:10px;text-decoration:none!important;font-weight:700;font-size:14px;margin:5px}
        .btn-primary{background:#0867DB;color:#fff!important}
        .btn-green{background:#059669;color:#fff!important}
        .btn-red{background:#dc2626;color:#fff!important}
        .btn-blue{background:#0867DB;color:#fff!important}
        .btn-cyan{background:#019FE6;color:#fff!important}
        .fallback{padding:0 28px 16px;font-size:12px;color:#94a3b8}
        .fallback a{color:#94a3b8;word-break:break-all}
        .footer{padding:18px 28px;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9;line-height:1.7}
        .footer a{color:#64748b;text-decoration:none}
        .footer-brand{margin-top:6px;color:#94a3b8}
        hr.divider{border:none;border-top:1px solid #f1f5f9;margin:16px 0}
        @media(max-width:480px){
            body{padding:8px}
            .card-header,.card-body,.card-actions,.fallback,.footer{padding-left:16px;padding-right:16px}
        }
    </style>
</head>
<body>
<div class="wrap">

    {{-- Masthead --}}
    <div class="logo-bar">
        <img src="https://mis.crc.pshs.edu.ph/images/atlas-logo-full.png" alt="Atlas — PSHS-CRC MIS" class="logo-img" height="28">
        <span class="logo-sub">Philippine Science High School – Caraga Region Campus</span>
    </div>

    <div class="card">

        {{-- Brand header (status is conveyed by badges/callouts in the body) --}}
        <div class="card-header">
            <h1>@yield('header-title','Notification')</h1>
            <p>@yield('header-subtitle','PSHS-CRC Management Information System')</p>
        </div>

        {{-- Main body --}}
        <div class="card-body">
            @yield('content')
        </div>

        {{-- Action buttons (optional) --}}
        @hasSection('actions')
        <div class="card-actions">
            @yield('actions')
        </div>
        @endif

        {{-- Fallback plain-text links (optional) --}}
        @hasSection('fallback-links')
        <div class="fallback">
            <p>If the buttons above do not work, copy and paste the following links into your browser:</p>
            @yield('fallback-links')
        </div>
        @endif

        {{-- Footer --}}
        <div class="footer">
            This is an automated notification from Atlas. Please do not reply to this email.
            @hasSection('footer-note')
            <br>@yield('footer-note')
            @endif
            <div class="footer-brand">© {{ date('Y') }} PSHS-CRC · <a href="https://mis.crc.pshs.edu.ph">Atlas — mis.crc.pshs.edu.ph</a></div>
        </div>

    </div>
</div>
</body>
</html>
