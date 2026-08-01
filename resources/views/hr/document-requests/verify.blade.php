<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>HR Document Verification — Atlas</title>
<style>
body{margin:0;background:#f1f5f9;color:#1e293b;font-family:Arial,sans-serif}.wrap{max-width:680px;margin:50px auto;padding:20px}.card{background:#fff;border-radius:18px;box-shadow:0 10px 35px #0f172a18;overflow:hidden}.head{background:#060e50;color:#fff;padding:24px}.body{padding:28px}.status{border-radius:12px;padding:16px;margin-bottom:22px;background:#ecfdf5;color:#166534}.bad{background:#fef2f2;color:#991b1b}.row{display:grid;grid-template-columns:170px 1fr;gap:16px;padding:12px 0;border-bottom:1px solid #e2e8f0}.label{color:#64748b;font-size:12px;text-transform:uppercase;font-weight:bold}.privacy{margin-top:22px;padding:14px;background:#f8fafc;border-radius:10px;color:#64748b;font-size:12px;line-height:1.5}@media(max-width:600px){.row{grid-template-columns:1fr;gap:4px}.wrap{margin:10px auto}}
</style>
</head>
<body><div class="wrap"><div class="card"><div class="head"><strong>PSHS-CRC Atlas</strong><br><span style="font-size:13px;opacity:.8">HR Document Verification</span></div><div class="body">
@if(!$valid)
<div class="status bad"><strong>Document not found</strong><br>This verification token does not match an issued HR document.</div>
@else
<div class="status"><strong>Authentic issuance record</strong><br>The control number and issuance metadata were found in Atlas.</div>
<div class="row"><div class="label">Control Number</div><div><strong>{{ $issued->control_number }}</strong></div></div>
<div class="row"><div class="label">Document Type</div><div>{{ $issued->request->type->name }}</div></div>
<div class="row"><div class="label">Employee</div><div>{{ $issued->request->requester->name }}</div></div>
<div class="row"><div class="label">Issued</div><div>{{ $issued->issued_at->format('F j, Y') }}</div></div>
<div class="row"><div class="label">Version</div><div>{{ $issued->version }}</div></div>
<div class="row"><div class="label">Record Status</div><div>{{ $fileExists ? 'Canonical file available' : 'Canonical file unavailable — contact HR' }}</div></div>
<div class="privacy">For employee privacy, the document contents and compensation details are not publicly displayed. Compare the control number, employee name, document type, and issue date with the presented copy.</div>
@endif
</div></div></div></body></html>
