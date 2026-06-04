<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Gate Pass {{ $row->controlno ?? '' }}</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: Arial, Helvetica, sans-serif; margin: 0; background: #fff; color: #000; }
    .page { width: 210mm; margin: 0 auto; padding: 10mm; }
    table { width: 100%; border-collapse: collapse; }
    td { font-size: 12px; vertical-align: top; padding: 0; }
    .b  { border: 1px solid #000; }
    .center { text-align: center; }
    .bold { font-weight: 700; }
    .checkbox {
      display: inline-block; width: 13px; height: 13px;
      border: 1px solid #000; vertical-align: middle;
      margin-right: 5px; position: relative;
    }
    .checkbox.checked::after {
      content: '✓'; font-size: 11px; line-height: 13px;
      position: absolute; left: 1px; top: -1px;
    }
    @media print {
      @page { size: A4 portrait; margin: 8mm; }
      body  { margin: 0; }
      .page { width: 100%; padding: 0; }
    }
    .dig-badge { font-size:9px; color:#166534; background:#f0fdf4; border:1px solid #86efac; border-radius:3px; padding:2px 6px; margin-top:3px; display:inline-block; }
    .sig-img { max-height:55px; display:block; margin:0 0 4px; }
  </style>
</head>
<body onload="window.print()">
<div class="page">

  <!-- ── Main Gate Pass Form ── -->
  <table class="b" style="margin-bottom: 6px;">

    <!-- Row 1: School name & campus -->
    <tr>
      <td colspan="4" class="b center" style="padding: 10px 8px;">
        <div class="bold" style="font-size: 14px;">PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</div>
        <div class="bold" style="font-size: 13px;">CAMPUS: <u>CARAGA REGION</u></div>
      </td>
    </tr>

    <!-- Row 2: Form title -->
    <tr>
      <td colspan="4" class="b center" style="padding: 8px;">
        <div class="bold" style="font-size: 13px;">ASSIGNMMENT SLIP / EMPLOYEE GATE PASS</div>
      </td>
    </tr>

    <!-- Row 3: Control No (label right-aligned spanning 3 cols, value in last col) -->
    <tr>
      <td colspan="3" class="b" style="padding: 6px 10px; text-align: right;">CONTROL NO:</td>
      <td class="b" style="width: 200px; padding: 6px 10px;">{{ $row->controlno ?? '' }}</td>
    </tr>

    <!-- Row 4: Date -->
    <tr>
      <td colspan="3" class="b" style="padding: 6px 10px; text-align: right;">DATE:</td>
      <td class="b" style="padding: 6px 10px;">{{ $row->gatepass_date ?? '' }}</td>
    </tr>

    <!-- Name -->
    <tr>
      <td class="b" style="width: 130px; padding: 7px 8px; font-weight: 700;">NAME:</td>
      <td class="b" colspan="3" style="padding: 7px 8px; font-weight: 600; text-transform: uppercase;">
        {{ $row->name ?? '' }}
      </td>
    </tr>

    <!-- Position -->
    <tr>
      <td class="b" style="padding: 7px 8px; font-weight: 700;">POSITION:</td>
      <td class="b" colspan="3" style="padding: 7px 8px;">{{ $row->position ?? '' }}</td>
    </tr>

    <!-- Time Out / Time In -->
    <tr>
      <td class="b" style="padding: 7px 8px; font-weight: 700;">TIME OUT:</td>
      <td class="b" style="padding: 7px 8px; width: 150px;">{{ $row->gatepass_timeout ?? '' }}</td>
      <td class="b" style="padding: 7px 8px; width: 90px; font-weight: 700;">TIME IN:</td>
      <td class="b" style="padding: 7px 8px;">{{ $row->gatepass_timein ?? '' }}</td>
    </tr>

    @if(!empty($row->actual_timeout) || !empty($row->actual_timein))
    <!-- Actual Time Out / Time In -->
    <tr>
      <td class="b" style="padding: 7px 8px; font-weight: 700; font-size: 11px;">ACTUAL OUT:</td>
      <td class="b" style="padding: 7px 8px; width: 150px;">{{ $row->actual_timeout ?? '' }}</td>
      <td class="b" style="padding: 7px 8px; width: 90px; font-weight: 700; font-size: 11px;">ACTUAL IN:</td>
      <td class="b" style="padding: 7px 8px;">{{ $row->actual_timein ?? '' }}</td>
    </tr>
    @endif

    <!-- Destination -->
    <tr>
      <td class="b" style="padding: 7px 8px; font-weight: 700;">DESTINATION:</td>
      <td class="b" colspan="3" style="padding: 7px 8px;">{{ $row->destination ?? '' }}</td>
    </tr>

    <!-- Purpose -->
    <tr>
      <td class="b" style="padding: 7px 8px; font-weight: 700;">PURPOSE:</td>
      <td class="b" colspan="3" style="padding: 7px 8px; min-height: 36px;">{{ $row->purpose ?? '' }}</td>
    </tr>

    <!-- Type checkboxes -->
    <tr>
      <td class="b" colspan="4" style="padding: 8px 12px;">
        <div style="display: flex; flex-direction: column; gap: 5px;">
          <div>
            <span class="checkbox {{ ($row->gatepass_type ?? '') === 'Official Business' ? 'checked' : '' }}"></span>
            Official Business
          </div>
          <div>
            <span class="checkbox {{ ($row->gatepass_type ?? '') === 'Office Time' ? 'checked' : '' }}"></span>
            Official Time
          </div>
          <div>
            <span class="checkbox {{ ($row->gatepass_type ?? '') === 'Personal' ? 'checked' : '' }}"></span>
            Personal
          </div>
        </div>
      </td>
    </tr>

    <!-- Recommending Approval label -->
    <tr>
      <td class="b" colspan="4" style="padding: 7px 8px;">Recommending Approval:</td>
    </tr>

    <!-- Division Chief signature area -->
    <tr>
      <td class="b" colspan="4" style="height: 80px; padding: 10px 12px; vertical-align: bottom;">
        @if(isset($sigs['dc_approval']['uri']))
          <img src="{{ $sigs['dc_approval']['uri'] }}" alt="division chief signature" class="sig-img" />
        @endif
        @if(isset($sigs['dc_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ $sigs['dc_approval']['name'] }} · {{ optional($sigs['dc_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
        @if(!empty($divisionChief['name']))
          <div class="bold" style="text-decoration: underline; text-transform: uppercase;">{{ $divisionChief['name'] }}</div>
          <div>Division Chief</div>
        @else
          <div>&nbsp;</div>
          <div>Division Chief</div>
        @endif
      </td>
    </tr>

    <!-- Approved label -->
    <tr>
      <td class="b" colspan="4" style="padding: 7px 8px;">Approved:</td>
    </tr>

    <!-- Director signature area -->
    <tr>
      <td class="b" colspan="4" style="height: 100px; padding: 10px 12px; vertical-align: bottom;">
        @if(isset($sigs['ocd_approval']['uri']))
          <img src="{{ $sigs['ocd_approval']['uri'] }}" alt="director signature" class="sig-img" />
        @endif
        @if(isset($sigs['ocd_approval'])) <div class="dig-badge">✓ Digitally Signed · {{ $sigs['ocd_approval']['name'] }} · {{ optional($sigs['ocd_approval']['signed_at'])->format('M d, Y H:i') }}</div> @endif
        @if(!empty($director['name']))
          <div class="bold" style="text-decoration: underline; text-transform: uppercase;">{{ $director['name'] }}</div>
          <div>Campus Director</div>
        @else
          <div style="height: 40px;">&nbsp;</div>
          <div>Campus Director</div>
        @endif
      </td>
    </tr>

    <!-- Form number -->
    <tr>
      <td colspan="4" style="padding: 5px 8px; font-size: 10px;">PSHS-00-F-HRU-16-Ver02-Rev1-10/18/20</td>
    </tr>

  </table>

  <!-- ── Certificate of Appearance (2 identical copies) ── -->
  <table style="border-collapse: collapse; width: 100%;">
    <tr>
      @for ($i = 0; $i < 2; $i++)
      <td class="b" style="width: 50%; padding: 10px; vertical-align: top; font-size: 11px; {{ $i === 0 ? 'border-right: none;' : '' }}">
        <div class="bold center" style="margin-bottom: 8px; font-size: 12px;">CERTIFICATE OF APPEARANCE.</div>
        <p style="margin: 0 0 4px;">
          This is to certify that I attended to Mr./ Ms.<u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u>,
        </p>
        <p style="margin: 0 0 4px;">
          of PSH-CRC &nbsp;&nbsp; on <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u>
          at <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u> a.m./p.m.
        </p>
        <p style="margin: 0 0 20px;">when he/she transcated business with my Agency/ Company.</p>

        <div style="height: 44px;"></div>

        <div style="width: 75%; margin: 0 auto; text-align: center; border-top: 1px solid #000; padding-top: 3px;">
          <div>Signature over Printed Name</div>
          <div>of Attending Employee/ Position</div>
        </div>

        <p style="margin: 10px 0 4px;">Date <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u></p>

        <p style="margin: 0 0 0;">Name of Agency/ies: <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u></p>
        <p style="margin: 0 0 10px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u></p>

        <p style="margin: 0 0 0;">Address: <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u></p>
        <p style="margin: 0 0 10px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u></p>

        <p style="margin: 0 0 16px;">Tel.No.: <u>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</u></p>

        <p style="margin: 0 0 4px; font-size: 10px;">In case an employee buys office supplies, said employee shall attach an authenticated copy of OR of purchase.</p>
        <p style="margin: 4px 0 0; font-size: 10px;">PSHS-00-F-HRU-16-Ver02-Rev1-10/18/20</p>
      </td>
      @endfor
    </tr>
  </table>

</div>
</body>
</html>
