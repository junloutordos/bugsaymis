@extends('alp.forms._layout')
@php
    $activities = data_get($report->data, 'activities', []);
    $assessment = data_get($report->data, 'assessment', []);
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'ALP COORDINATOR ACCOMPLISHMENT REPORT',
    'periodLine' => 'Quarter, S.Y. '.$cycle->schoolYear->name,
])

<p><strong>Implemented activities (ALP Coordinator only, e.g., orientation, quarterly meeting, capacity-building, etc.):</strong></p>
<table class="bordered">
    <thead><tr><th>Date/Month</th><th>Activity/Program</th><th>Learning Outcome/s</th><th>Participants</th><th>Venue</th><th>Remark/s</th></tr></thead>
    <tbody>
    @foreach($activities as $activity)
        <tr>
            <td>{{ data_get($activity, 'date') }}</td>
            <td>{{ data_get($activity, 'title') }}</td>
            <td>{{ data_get($activity, 'learning_outcomes') }}</td>
            <td>{{ data_get($activity, 'participants') }}</td>
            <td>{{ data_get($activity, 'venue') }}</td>
            <td>{{ data_get($activity, 'remarks') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<p><strong>Summary Assessment of ALP implementation (based on the report of ALP Advisers and observation):</strong></p>
<table class="bordered">
    <thead><tr><th>Strength</th><th>Weakness</th><th>Gap</th><th>Recommendation</th></tr></thead>
    <tbody>
    @foreach($assessment as $row)
        <tr>
            <td>{{ data_get($row, 'strength') }}</td>
            <td>{{ data_get($row, 'weakness') }}</td>
            <td>{{ data_get($row, 'gap') }}</td>
            <td>{{ data_get($row, 'recommendation') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@include('alp.forms._signatures', ['rows' => [
    [
        ['action' => 'Prepared by:', 'name' => $signatories['coordinator'] ?? null, 'role' => 'ALP Coordinator'],
        ['action' => 'Noted by:', 'name' => null, 'role' => 'Assistant CID Chief for Student Affairs/DSA Chief'],
    ],
]])
@endsection
