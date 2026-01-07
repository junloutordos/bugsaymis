<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Trip Ticket - {{ $request->id }}</title>
  <style>
    body{font-family:Arial, Helvetica, sans-serif; color:#0f172a; padding:20px}
    .container{max-width:900px;margin:0 auto}
    .header{text-align:center;margin-bottom:12px}
    .card{border:1px solid #ccc;padding:12px;border-radius:6px}
    table{width:100%;border-collapse:collapse}
    td,th{padding:6px;border:1px solid #ddd;font-size:13px}
    .no-border td{border:0;padding:2px}
    .center{text-align:center}
    @media print{ .no-print{display:none} }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h2>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</h2>
      <div>CAMPUS: CARAGA REGION CAMPUS IN BUTUAN CITY</div>
      <h3>TRIP TICKET / PERMIT TO USE VEHICLE</h3>
    </div>

    <div class="card">
      <table>
        <tr>
          <td><strong>No.</strong></td>
          <td>{{ $request->id }}</td>
          <td><strong>Date Filed</strong></td>
          <td>{{ optional($request->created_at)->toDateString() }}</td>
        </tr>
        <tr>
          <td><strong>Vehicle Requested</strong></td>
          <td>{{ $request->vehicle_type ?? '—' }}</td>
          <td><strong>Date(s) of Trip</strong></td>
          <td>
            @if(!empty($request->date_needed_multiple) && is_array($request->date_needed_multiple))
              @php
                $dates = $request->date_needed_multiple;
                $start = $dates[0] ?? null;
                $end = $dates[count($dates) - 1] ?? null;
              @endphp
              {{ $start && $end ? ($start . ' to ' . $end) : (is_array($dates) ? implode(', ', $dates) : $start) }}
            @else
              {{ optional($request->date_needed)->toDateString() ?? '—' }}
            @endif
          </td>
        </tr>
        <tr>
          <td><strong>Time of Departure</strong></td>
          <td>{{ $request->time_of_departure ?? '—' }}</td>
          <td><strong>Time of Arrival</strong></td>
          <td>{{ $request->eta ?? '—' }}</td>
        </tr>
        <tr>
          <td><strong>No. of Passengers</strong></td>
          <td>{{ $request->passengers ?? '—' }}</td>
          <td><strong>Destination(s)</strong></td>
          <td>{{ $request->destination ?? '—' }}</td>
        </tr>
        <tr>
          <td><strong>Purpose</strong></td>
          <td colspan="3">{{ $request->purpose ?? '—' }}</td>
        </tr>
        <tr>
          <td><strong>Requested by</strong></td>
          <td>{{ $request->user->name ?? '—' }}</td>
          <td><strong>Division Chief</strong></td>
          <td>{{ $request->divisionChief->name ?? '—' }}</td>
        </tr>
        <tr>
          <td><strong>Driver</strong></td>
          <td>{{ $request->driver->name ?? '—' }}</td>
          <td><strong>Status</strong></td>
          <td>{{ $request->status }}</td>
        </tr>
      </table>

      <div style="margin-top:18px;display:flex;justify-content:space-between">
        <div style="text-align:center">
          <div>Requested by:</div>
          <div style="margin-top:40px;font-weight:bold">{{ $request->user->name ?? '—' }}</div>
        </div>
        <div style="text-align:center">
          <div>Division Chief:</div>
          <div style="margin-top:40px;font-weight:bold">{{ $request->divisionChief->name ?? '—' }}</div>
        </div>
        <div style="text-align:center">
          <div>Driver:</div>
          <div style="margin-top:40px;font-weight:bold">{{ $request->driver->name ?? '—' }}</div>
        </div>
      </div>

      <div class="no-print" style="margin-top:18px;text-align:right">
        <button onclick="window.print()">Print</button>
      </div>
    </div>
  </div>
</body>
</html>
