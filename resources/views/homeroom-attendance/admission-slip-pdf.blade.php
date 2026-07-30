<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body { font-family: Arial, Helvetica, sans-serif; font-size: 8pt; color: #0f172a; }

    .header { text-align: center; margin-bottom: 2mm; }
    .header .system { font-size: 9pt; font-weight: bold; text-transform: uppercase; }
    .title { text-align: center; font-size: 10pt; font-weight: bold; text-transform: uppercase; margin: 2mm 0; }

    table.fields { width: 100%; font-size: 8pt; margin-top: 2mm; }
    table.fields td { padding: 1mm 0; vertical-align: top; }
    table.fields .label { font-weight: bold; width: 32%; }

    .excused-stamp { text-align: center; font-weight: bold; font-size: 10pt; margin-top: 3mm; text-transform: uppercase; }
    .excused { color: #15803d; }
    .unexcused { color: #b91c1c; }

    .sig { margin-top: 6mm; font-size: 7.5pt; }
    .sig-line { border-top: 1px solid #0f172a; width: 55mm; margin-top: 8mm; padding-top: 1mm; }
  </style>
</head>
<body>
  <div class="header">
    <div class="system">Philippine Science High School System — CRC</div>
  </div>
  <div class="title">Class Admission Slip</div>

  <table class="fields">
    <tr><td class="label">Student:</td><td>{{ $slip->student->lastname }}, {{ $slip->student->firstname }} {{ $slip->student->middlename }}</td></tr>
    <tr><td class="label">Section:</td><td>Grade {{ $slip->section->levelid }} - {{ $slip->section->sectionname }}</td></tr>
    <tr><td class="label">Date of Infraction:</td><td>{{ \Carbon\Carbon::parse($slip->infraction_date)->format('F j, Y') }}</td></tr>
    <tr><td class="label">Type:</td><td>{{ $slip->infraction_type_label }}</td></tr>
    <tr><td class="label">Reason:</td><td>{{ $slip->reason ?: '—' }}</td></tr>
  </table>

  <div class="excused-stamp {{ $slip->excused_status }}">{{ strtoupper($slip->excused_status) }}</div>

  <div class="sig">
    <div class="sig-line">{{ $registrarName ?? '&nbsp;' }}</div>
    School Registrar
  </div>
</body>
</html>
