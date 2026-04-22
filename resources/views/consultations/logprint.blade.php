<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>{{ (request()->query('type') ?? (request()->route() && str_contains(request()->route()->getName(), 'employee') ? 'employee' : 'student')) === 'both' ? 'Patient Consultation' : ((request()->query('type') ?? (request()->route() && str_contains(request()->route()->getName(), 'employee') ? 'employee' : 'student')) === 'employee' ? 'Employee Consultation' : 'Student Consultation') }}</title>
  <style>
    @page { size: A4 landscape; margin: 20mm; }
    body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #111; }
    .header { text-align: center; margin-bottom: 12px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #444; padding: 6px 8px; text-align: left; }
    th { background: #f3f3f3; }
  </style>
</head>
<body>
  <div class="header">
    <h2>{{ ($type ?? (request()->query('type') ?? (request()->route() && str_contains(request()->route()->getName(), 'employee') ? 'employee' : 'student'))) === 'both' ? 'PATIENT CONSULTATION' : (( $type ?? (request()->query('type') ?? (request()->route() && str_contains(request()->route()->getName(), 'employee') ? 'employee' : 'student'))) === 'employee' ? 'EMPLOYEE CONSULTATION' : 'STUDENT CONSULTATION') }}</h2>
  
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Date Attended</th>
        <th>Time In</th>
        <th>
          @php
            $viewType = $type ?? request()->query('type') ?? (request()->route() && str_contains(request()->route()->getName(), 'employee') ? 'employee' : 'student');
          @endphp
          {{ $viewType === 'both' ? 'Patient Name' : ($viewType === 'employee' ? 'Employee Name' : 'Name of Student') }}
        </th>
        <th>Sex</th>
        <th>
          {{ $viewType === 'both' ? 'Grade &amp; Section / Division' : ($viewType === 'employee' ? 'Division' : 'Grade &amp; Section') }}
        </th>
        <th>Nature of Complaint</th>
        <th>Vital Signs</th>
        <th>Action Taken</th>
        <th>Medicine Given</th>
        <th>Time Out</th>
        <th>Disposition</th>
      </tr>
    </thead>
    <tbody>
      @forelse($consultations as $c)
        <tr>
          <td>{{ $c->id }}</td>
          <td>{{ optional($c->date_attended) ? \Carbon\Carbon::parse($c->date_attended)->toFormattedDateString() : '—' }}</td>
          <td>{{ $c->time_start ?? '—' }}</td>
          <td>{{ $c->requestor_data->name ?? '—' }}</td>
          <td>{{ $c->requestor_data->sex ?? '—' }}</td>
          <td>
            @if(($type ?? request()->query('type')) === 'both')
              @if(isset($c->requestor_type) && $c->requestor_type === 'employee')
                {{ $c->requestor_data->office ?? '—' }}
              @else
                {{ ($c->requestor_data->grade_level ?? '') . ($c->requestor_data->section ? ' · ' . $c->requestor_data->section : '') }}
              @endif
            @elseif(request()->route() && str_contains(request()->route()->getName(), 'employee'))
              {{ $c->requestor_data->office ?? '—' }}
            @else
              {{ ($c->requestor_data->grade_level ?? '') . ($c->requestor_data->section ? ' · ' . $c->requestor_data->section : '') }}
            @endif
          </td>
          <td>{{ $c->concern_display ?? '—' }}</td>
          <td>{{ $c->vitals_display ?? '—' }}</td>
          <td>{{ $c->action_taken_display ?? '—' }}</td>
          <td>{{ $c->medicine_given_display ?? '—' }}</td>
          <td>{{ $c->time_out_display ?? '—' }}</td>
          <td>{{ $c->disposition_display ?? '—' }}</td>
        </tr>
      @empty
        <tr><td colspan="11" style="text-align:center;">No records found.</td></tr>
      @endforelse
    </tbody>
  </table>

  <script>
    // Auto-print when opened
    window.onload = function() { window.print(); }
  </script>
</body>
</html>
