<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('email-title', 'PSHS-CRC MIS Notification')</title>
    <style>
        *{box-sizing:border-box}
        body{background:#f1f5f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;color:#334155;margin:0;padding:20px 12px}
        .wrap{max-width:600px;margin:0 auto}
        .logo-bar{text-align:center;padding:10px 0 14px}
        .logo-name{font-size:14px;font-weight:700;color:#1e40af;letter-spacing:.03em}
        .logo-sub{font-size:11px;color:#64748b;display:block;margin-top:2px}
        .card{background:#fff;border-radius:12px;box-shadow:0 4px 24px rgba(15,23,42,.08);overflow:hidden}
        .card-header{padding:24px 28px;color:#fff}
        .card-header h1{font-size:19px;font-weight:700;margin:0 0 4px;line-height:1.3}
        .card-header p{margin:0;font-size:13px;opacity:.85}
        .card-body{padding:24px 28px}
        .greeting{font-size:15px;font-weight:500;color:#1e293b;margin:0 0 12px}
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
        .badge-blue{background:#dbeafe;color:#1e40af}
        .badge-cyan{background:#cffafe;color:#155e75}
        .badge-slate{background:#f1f5f9;color:#475569}
        .priority-urgent{background:#fce7f3;color:#9d174d;font-weight:700}
        .priority-high{background:#fee2e2;color:#991b1b}
        .priority-normal{background:#e0f2fe;color:#075985}
        .priority-low{background:#f1f5f9;color:#475569}
        .callout{border-radius:8px;padding:14px 16px;margin:18px 0 4px;font-size:14px;line-height:1.55}
        .callout-blue{background:#eff6ff;border-left:4px solid #3b82f6;color:#1e3a8a}
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
        .card-actions{padding:6px 28px 22px;text-align:center}
        .btn{display:inline-block;padding:12px 26px;border-radius:8px;text-decoration:none!important;font-weight:700;font-size:14px;margin:5px}
        .btn-primary{background:#4f46e5;color:#fff!important}
        .btn-green{background:#059669;color:#fff!important}
        .btn-red{background:#dc2626;color:#fff!important}
        .btn-blue{background:#1447c0;color:#fff!important}
        .btn-cyan{background:#0891b2;color:#fff!important}
        .fallback{padding:0 28px 16px;font-size:12px;color:#94a3b8}
        .fallback a{color:#94a3b8;word-break:break-all}
        .footer{padding:16px 28px;font-size:12px;color:#94a3b8;border-top:1px solid #f1f5f9;line-height:1.65}
        hr.divider{border:none;border-top:1px solid #f1f5f9;margin:16px 0}
        @media(max-width:480px){
            body{padding:8px}
            .card-header,.card-body,.card-actions,.fallback,.footer{padding-left:16px;padding-right:16px}
        }
    </style>
</head>
<body>
<div class="wrap">

    {{-- School identifier --}}
    <div class="logo-bar">
        <span class="logo-name">PSHS–CRC MIS</span>
        <span class="logo-sub">Philippine Science High School – Caraga Region Campus</span>
    </div>

    <div class="card">

        {{-- Colored header --}}
        <div class="card-header" style="background:@yield('header-gradient','linear-gradient(90deg,#4f46e5,#6366f1)')">
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
            This is an automated notification from PSHS-CRC MIS. Please do not reply to this email.
            @hasSection('footer-note')
            <br>@yield('footer-note')
            @endif
        </div>

    </div>
</div>
</body>
</html>
