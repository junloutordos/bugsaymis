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

    .summary { width: 100%; margin-top: 3mm; font-size: 8pt; }
    .summary td { padding: 0.5mm 4mm 0.5mm 0; }
    .summary .label { font-weight: bold; }

    .signatures { width: 100%; margin-top: 10mm; font-size: 8pt; }
    .signatures td { width: 50%; vertical-align: top; padding-top: 6mm; }
    .sig-line { border-top: 1px solid #0f172a; width: 65mm; margin-top: 10mm; padding-top: 1mm; }
    .sig-role { font-size: 7.5pt; color: #475569; }
  </style>
</head>
<body>
  <div class="header">
    <div class="system">Philippine Science High School System</div>
    <div class="campus">Campus: CRC in Butuan City</div>
  </div>

  <div class="title">{{ $eventLabel }} Attendance</div>

  <table class="meta">
    <tr>
      <td class="label" width="18%">Grade and Section:</td>
      <td width="32%">Grade {{ $section->levelid }} - {{ $section->sectionname }}</td>
      <td class="label" width="20%">Date:</td>
      <td width="30%">{{ $attendance->date->translatedFormat('F j, Y') }}</td>
    </tr>
  </table>

  <table class="roster">
    <thead>
      <tr>
        <th width="6%">No.</th>
        <th width="54%">Name of Students</th>
        <th width="13%">P</th>
        <th width="13%">A</th>
        <th width="14%">T</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($students as $i => $record)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td class="name">{{ $record->student->lastname }}, {{ $record->student->firstname }} {{ $record->student->middlename }}</td>
          <td>{{ $record->status === 'present' ? 'X' : '' }}</td>
          <td>{{ $record->status === 'absent' ? 'X' : '' }}</td>
          <td>{{ $record->status === 'tardy' ? 'X' : '' }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="summary">
    <tr>
      <td class="label">Summary:</td>
      <td><span class="label">Present:</span> {{ $students->where('status', 'present')->count() }}</td>
      <td><span class="label">Absent:</span> {{ $students->where('status', 'absent')->count() }}</td>
      <td><span class="label">Tardy:</span> {{ $students->where('status', 'tardy')->count() }}</td>
    </tr>
  </table>

  <table class="signatures">
    <tr>
      <td>
        <div class="sig-line">{{ $adviserName ?? '&nbsp;' }}</div>
        <div class="sig-role">Homeroom Adviser/Academic Adviser (Prepared by)</div>
      </td>
      <td>
        <div class="sig-line">&nbsp;</div>
        <div class="sig-role">Homeroom Unit Coordinator (Checked by)</div>
      </td>
    </tr>
  </table>
</body>
</html>
