<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Gate Pass {{ $row->controlno ?? '' }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family: Arial, Helvetica, sans-serif; color:#000;}
    .container{width:820px;margin:0 auto;padding:6px}
    table{width:100%;border-collapse:collapse}
    .b{border:1px solid #000}
    td, th{padding:8px;font-size:13px}
    .center{text-align:center}
    .right{text-align:right}
    .small{font-size:12px}
    .signature{height:70px}
    .title{font-weight:700}
    .label{width:160px;font-weight:700}
    .checkbox{display:inline-block;width:18px;height:18px;border:1px solid #000;margin-right:8px;vertical-align:middle}
    .checkbox.checked{background:#000}
  </style>
</head>
<body onload="window.print()">
  <div class="container">
    <table style="width:100%;border-collapse:collapse;border:1px solid #000">
      <tr>
        <td class="center" colspan="3" style="border-right:1px solid #000;padding:8px">
          <div style="font-weight:800">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</div>
          <div style="font-weight:700">CAMPUS: <u>CARAGA REGION</u></div>
          <br>
          <br>
          <div style="font-weight:700">ASSIGNMMENT SLIP / EMPLOYEE GATE PASS</div>
        </td>
        <td style="width:220px;padding:8px;vertical-align:top">
          <table style="width:100%;border-collapse:collapse">
            <tr>
              <td style="border:1px solid #000;padding:6px;font-weight:700">CONTROL NO:</td>
            </tr>
            <tr>
              <td style="border:1px solid #000;padding:10px;text-align:center">{{ $row->controlno ?? '' }}</td>
            </tr>
            <tr>
              <td style="border:1px solid #000;padding:6px;font-weight:700">DATE:</td>
            </tr>
            <tr>
              <td style="border:1px solid #000;padding:10px;text-align:center">{{ $row->gatepass_date ?? '' }}</td>
            </tr>
          </table>
        </td>
      </tr>

      <tr>
        <td class="b" style="padding:8px"><span class="label">NAME:</span></td>
        <td class="b" colspan="3" style="padding:8px;text-transform:uppercase">{{ $row->name ?? $row->employee_name ?? $row->fullname ?? '' }}</td>
      </tr>
      <tr>
        <td class="b" style="padding:8px"><span class="label">POSITION:</span></td>
        <td class="b" colspan="3" style="padding:8px">{{ $row->position ?? $row->job_title ?? '' }}</td>
      </tr>
      <tr>
        <td class="b" style="padding:8px"><strong>TIME OUT:</strong></td>
        <td class="b" style="padding:8px;width:200px">{{ $row->gatepass_timein ?? $row->gatepass_timeout ?? '' }}</td>
        <td class="b" style="padding:8px;width:120px"><strong>TIME IN:</strong></td>
        <td class="b" style="padding:8px;width:200px">{{ $row->gatepass_timeout ?? $row->gatepass_timein ?? '' }}</td>
      </tr>
      <tr>
        <td class="b" style="padding:8px"><strong>DESTINATION:</strong></td>
        <td class="b" colspan="3" style="padding:8px">{{ $row->destination ?? '' }}</td>
      </tr>
      <tr>
        <td class="b" style="padding:8px"><strong>PURPOSE:</strong></td>
        <td class="b" colspan="3" style="padding:8px">{{ $row->purpose ?? '' }}</td>
      </tr>
      <tr>
        <td class="b" colspan="4" style="padding:8px">
          <div style="display:flex;gap:24px;padding:6px">
            <div><span class="checkbox {{ ($row->gatepass_type ?? '') === 'Official Business' ? 'checked' : '' }}"></span>Official Business</div>
            <div><span class="checkbox {{ ($row->gatepass_type ?? '') === 'Office Time' ? 'checked' : '' }}"></span>Official Time</div>
            <div><span class="checkbox {{ ($row->gatepass_type ?? '') === 'Personal' ? 'checked' : '' }}"></span>Personal</div>
          </div>
        </td>
      </tr>

      <tr>
        <td class="b" colspan="4" style="padding:8px">Recommending Approval:</td>
      </tr>
      <tr>
        <td class="b" colspan="4" style="height:90px;padding:12px;vertical-align:bottom">
          <div style="font-weight:800;text-transform:uppercase">{{ $divisionChief['name'] ?? '' }}</div>
          <div>Division Chief</div>
        </td>
      </tr>

      <tr>
        <td class="b" colspan="4" style="padding:8px">Approved:</td>
      </tr>
      <tr>
        <td class="b" colspan="4" style="height:110px;padding:12px;vertical-align:bottom">
          <div style="font-weight:800;text-transform:uppercase">{{ $director['name'] ?? '' }}</div>
          <div>Director</div>
        </td>
      </tr>

      <tr>
        <td colspan="4" style="padding:6px;font-size:11px">PSHS-00-F-HRU-16-Ver02-Rev1-10/18/20</td>
      </tr>
    </table>
  </div>
</body>
</html>
