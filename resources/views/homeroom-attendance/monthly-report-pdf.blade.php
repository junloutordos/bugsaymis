<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #0f172a; }

    .header { text-align: center; margin-bottom: 3mm; }
    .header .system { font-size: 10pt; font-weight: bold; text-transform: uppercase; }
    .header .campus { font-size: 8pt; margin-top: 1mm; }
    .title { text-align: center; font-size: 11pt; font-weight: bold; text-transform: uppercase; margin: 3mm 0; letter-spacing: 0.5px; }

    .meta { width: 100%; font-size: 8pt; margin-bottom: 3mm; }
    .meta td { padding: 1mm 0; }
    .meta .label { font-weight: bold; }

    table.roster { width: 100%; border-collapse: collapse; margin-bottom: 2mm; }
    table.roster th, table.roster td { border: 1px solid #64748b; padding: 1.2mm 1.5mm; font-size: 7pt; text-align: center; }
    table.roster th { background: #f1f5f9; font-weight: bold; }
    table.roster td.name { text-align: left; }
    table.roster tr.group-header td { background: #e2e8f0; font-weight: bold; text-align: left; text-transform: uppercase; }

    .signatures { width: 100%; margin-top: 8mm; font-size: 8pt; }
    .signatures td { width: 50%; vertical-align: top; padding-top: 6mm; }
    .sig-line { border-top: 1px solid #0f172a; width: 65mm; margin-top: 10mm; padding-top: 1mm; }
    .sig-role { font-size: 7.5pt; color: #475569; }

    .status-badge { display: inline-block; padding: 0.5mm 2mm; border-radius: 2mm; font-size: 7pt; text-transform: uppercase; }
    .status-draft { background: #f1f5f9; }
    .status-submitted { background: #fef9c3; }
    .status-approved { background: #dcfce7; }
  </style>
</head>
<body>
  <div class="header">
    <div class="system">Philippine Science High School System</div>
    <div class="campus">Campus: CRC in Butuan City</div>
  </div>

  <div class="title">Record on Attendance and Punctuality</div>

  <table class="meta">
    <tr>
      <td class="label" width="18%">Year &amp; Section:</td>
      <td width="32%">Grade {{ $section->levelid }} - {{ $section->sectionname }}</td>
      <td class="label" width="20%">For the Month of:</td>
      <td width="30%">{{ $monthLabel }}</td>
    </tr>
    <tr>
      <td class="label">No. of School Days:</td>
      <td>{{ $report->school_days_count }}</td>
      <td class="label">Status:</td>
      <td><span class="status-badge status-{{ $report->status }}">{{ ucfirst($report->status) }}</span></td>
    </tr>
  </table>

  <table class="roster">
    <thead>
      <tr>
        <th width="4%">No.</th>
        <th width="26%">Names</th>
        <th width="8%">No. of Days Present</th>
        <th width="8%">Excused Absences</th>
        <th width="8%">Unexcused Absences</th>
        <th width="8%">No. of Cutting Classes</th>
        <th width="8%">No. of Times Tardy</th>
        <th width="8%">No. of Inc. Uniform</th>
        <th width="22%">Remarks</th>
      </tr>
    </thead>
    <tbody>
      @foreach (['BOYS' => $boys, 'GIRLS' => $girls] as $groupLabel => $lines)
        <tr class="group-header"><td colspan="9">{{ $groupLabel }}</td></tr>
        @foreach ($lines as $i => $line)
          <tr>
            <td>{{ $i + 1 }}</td>
            <td class="name">{{ $line->student->lastname }}, {{ $line->student->firstname }} {{ $line->student->middlename }}</td>
            <td>{{ number_format((float) $line->days_present, 2) }}</td>
            <td>{{ $line->excused_absences }}</td>
            <td>{{ $line->unexcused_absences }}</td>
            <td>{{ $line->cutting_count }}</td>
            <td>{{ $line->tardy_count }}</td>
            <td>{{ $line->inc_uniform_count }}</td>
            <td class="name">{{ $line->remarks }}</td>
          </tr>
        @endforeach
      @endforeach
    </tbody>
  </table>

  <table class="signatures">
    <tr>
      <td>
        <div class="sig-line">{{ $adviserName ?? '&nbsp;' }}</div>
        <div class="sig-role">Homeroom Adviser (Prepared by)</div>
      </td>
      <td>
        <div class="sig-line">{{ $coordinatorName ?? '&nbsp;' }}</div>
        <div class="sig-role">Homeroom Unit Coordinator (Checked by)</div>
      </td>
    </tr>
  </table>

  @if ($report->coordinator_remarks)
    <p style="margin-top: 4mm; font-size: 8pt;"><strong>Coordinator remarks:</strong> {{ $report->coordinator_remarks }}</p>
  @endif
</body>
</html>
