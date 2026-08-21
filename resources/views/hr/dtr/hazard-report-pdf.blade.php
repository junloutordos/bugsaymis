<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
* { box-sizing:border-box; }
body { font-family:Arial,sans-serif; color:#1e293b; font-size:9pt; line-height:1.4; }
.title { text-align:center; font-weight:bold; font-size:14pt; color:#060e50; text-transform:uppercase; letter-spacing:1px; margin:0 0 4px; }
.subtitle { text-align:center; font-size:9pt; color:#475569; margin-bottom:16px; }
.roster { width:100%; border-collapse:collapse; font-size:8pt; }
.roster th, .roster td { border:1px solid #64748b; padding:5px 6px; vertical-align:top; }
.roster th { background:#e2e8f0; text-transform:uppercase; font-size:7pt; text-align:left; }
.roster td.num { text-align:center; width:6%; }
.roster td.name { width:32%; }
.roster td.category { width:18%; }
.roster td.center { text-align:center; }
.roster td.total { text-align:center; font-weight:bold; }
.empty td { text-align:center; font-style:italic; color:#475569; }
.note { margin-top:10px; font-size:7.5pt; color:#64748b; }
</style>
</head>
<body>

<div class="title">Hazard Report</div>
<div class="subtitle">
  Basis for Hazard Pay — {{ \Carbon\Carbon::parse($date_from)->format('F j, Y') }}
  to {{ \Carbon\Carbon::parse($date_to)->format('F j, Y') }}
</div>

<table class="roster">
  <thead>
    <tr>
      <th class="num">#</th>
      <th>Employee Name</th>
      <th>Category</th>
      <th class="center">Full Days<br>(&ge;6h)</th>
      <th class="center">Half Days<br>(4&ndash;6h)</th>
      <th class="center">Total Hazard<br>Actual Exposure Days</th>
    </tr>
  </thead>
  <tbody>
    @forelse($rows as $i => $row)
      <tr>
        <td class="num">{{ $i + 1 }}</td>
        <td class="name">{{ $row['name'] }}</td>
        <td class="category">{{ str_contains($row['emp_category'], 'Non-Teaching') ? 'Non-Teaching' : 'Teaching' }}</td>
        <td class="center">{{ $row['full_days'] }}</td>
        <td class="center">{{ $row['half_days'] }}</td>
        <td class="total">{{ rtrim(rtrim(number_format($row['total_hazard_days'], 1), '0'), '.') }}</td>
      </tr>
    @empty
      <tr class="empty">
        <td colspan="6">No active Plantilla employees found.</td>
      </tr>
    @endforelse
  </tbody>
</table>

<p class="note">
  A day counts as 1.0 Hazard Actual Exposure day at 6+ effective hours present, 0.5 day at 4&ndash;6 hours,
  and is not counted below 4 hours. Work-from-home days, official travel days, and self-declared (penned)
  time not yet reviewed by HR are excluded.
</p>

</body>
</html>
