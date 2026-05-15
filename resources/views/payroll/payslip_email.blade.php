<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Payslip</title></head>
<body style="font-family:Arial,sans-serif;font-size:10pt;background:#f4f4f4;padding:20px;">
<div style="max-width:760px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.1);">

  <div style="background:#1e3a5f;color:#fff;padding:16px 24px;">
    <div style="font-size:8pt;opacity:.8;">Republic of the Philippines · Department of Science and Technology</div>
    <div style="font-size:13pt;font-weight:bold;margin-top:2px;">Philippine Science High School — Caraga Region Campus</div>
    <div style="font-size:8pt;opacity:.8;">Brgy. Ampayon, Butuan City</div>
  </div>

  <div style="padding:20px 24px 8px;">
    <p>Dear <strong>{{ $firstName }}</strong>,</p>
    <p style="margin-top:8px;">
      Please find below your payslip for
      <strong>{{ \Carbon\Carbon::parse($batch->period_start)->format('F j') }} –
      {{ \Carbon\Carbon::parse($batch->period_end)->format('F j, Y') }}</strong>.
      A PDF copy is also attached for your records.
    </p>
  </div>

  <div style="padding:0 12px 12px;">
    @include('payroll.payslip', ['item' => $item, 'batch' => $batch, 'preparedBy' => $preparedBy, 'certifiedBy' => $certifiedBy])
  </div>

  <div style="padding:12px 24px;background:#f9f9f9;border-top:1px solid #eee;font-size:8pt;color:#666;">
    This is a system-generated message. If you spot a discrepancy, reply to this email or contact the Cashier's Office.<br>
    PSHS-CRC Management Information System &mdash; <em>Do not reply directly to this email.</em>
  </div>

</div>
</body>
</html>
