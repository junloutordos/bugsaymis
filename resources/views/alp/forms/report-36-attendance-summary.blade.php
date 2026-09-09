@extends('alp.forms._layout')
@php
    $rows = data_get($report->data, 'rows', []);
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'ATTENDANCE SUMMARY REPORT FORM',
    'periodLine' => 'Semester, S.Y. '.$cycle->schoolYear->name,
])

<p class="field-line">Name of ALP: <span class="fill">{{ $cycle->program->name }}</span></p>
<p class="field-line">ALP Adviser: <span class="fill">{{ $signatories['adviser'] ?? '' }}</span></p>

<table class="bordered">
    <thead><tr><th style="width:5%">#</th><th>Names</th><th># of Present days</th><th># of Absences</th><th># of Tardiness</th><th># of Cutting class</th><th>Other remarks</th></tr></thead>
    <tbody>
    @foreach($rows as $row)
        <tr>
            <td>{{ $loop->iteration }}.</td>
            <td>{{ data_get($row, 'name') }}</td>
            <td style="text-align:center">{{ data_get($row, 'present') }}</td>
            <td style="text-align:center">{{ data_get($row, 'absent') }}</td>
            <td style="text-align:center">{{ data_get($row, 'tardy') }}</td>
            <td style="text-align:center">{{ data_get($row, 'cutting') }}</td>
            <td>{{ data_get($row, 'excused') ? data_get($row, 'excused').' excused' : '' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@include('alp.forms._signatures', ['rows' => [
    [
        ['action' => 'Prepared by:', 'name' => $signatories['adviser'] ?? null, 'role' => 'ALP Adviser'],
        ['action' => 'Noted by:', 'name' => $signatories['coordinator'] ?? null, 'role' => 'ALP Coordinator'],
    ],
    [['action' => 'Approved by:', 'name' => null, 'role' => 'Assistant CID Chief for Student Affairs/DSA Chief']],
]])
@endsection
