<div class="wat-heading">
  <h1><b>Weekly Assessment Tracker</b></h1>
  <table class="wat-meta">
    <tr>
      <td><strong>Section:</strong> Grade {{ $section['level'] }} — {{ $section['name'] }}</td>
      <td><strong>Week:</strong> {{ \Carbon\Carbon::parse($wat['week_start'])->format('F j, Y') }} – {{ \Carbon\Carbon::parse($wat['week_end'])->format('F j, Y') }}</td>
      <td><strong>School Year:</strong> {{ $schoolYear['name'] ?? '' }}</td>
    </tr>
  </table>
</div>
