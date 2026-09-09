@extends('alp.forms._layout')
@php
    $activities = $cycle->activities->sortBy('start_date');
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'CALENDAR OF ACTIVITIES',
    'periodLine' => 'Semester, S.Y. '.$cycle->schoolYear->name,
])

<table class="bordered">
    <thead><tr><th>Date/Month</th><th>Activity/Program</th><th>Learning Outcome/s</th><th>Target Participants</th><th>Venue</th><th>Budget/Resources needed</th></tr></thead>
    <tbody>
    @foreach($activities as $activity)
        <tr>
            <td>{{ $activity->start_date?->format('M j, Y') }}</td>
            <td>{{ $activity->title }}</td>
            <td>{{ $activity->learning_outcomes }}</td>
            <td>{{ $activity->target_participants }}</td>
            <td>{{ $activity->venue }}</td>
            <td>PHP {{ number_format((float) $activity->budget_amount, 2) }}<br>{{ $activity->resources_needed }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<div class="note-block">
    <div class="note-title">Note for ALP Coordinator and Adviser:</div>
    <p><strong>Date/month</strong>: Specific day, week, or month (e.g. June 12, First week of June).</p>
    <p><strong>Activity/Program</strong>: Title of activity (e.g. Enlistment, Orientation, Leadership Camp, Drills and Practice, Introduction to PH Agriculture, etc.).</p>
    <p><strong>Learning Outcome/s:</strong> Alignment with ALP's nature and objectives, ALP guidelines, and general capabilities of a PSHS graduate (e.g. leadership, critical thinking, life-long learner, critical thinker, etc.).</p>
    <p><strong>Target Participants</strong>: Must identify involvement of students such as ALP members, Grades 11-12, personnel involved or invited guests (only if applicable).</p>
    <p><strong>Venue</strong>: Identify specific location and classify if inside or outside campus.</p>
    <p><strong>Budget/Resources Needed</strong>: Materials needed, logistics, budget, etc.</p>
</div>

@include('alp.forms._signatures', ['rows' => [
    [
        ['action' => 'Prepared by:', 'name' => $signatories['president'] ?? null, 'role' => 'ALP President'],
        ['action' => 'Approved by:', 'name' => $signatories['adviser'] ?? null, 'role' => 'ALP Adviser'],
    ],
    [
        ['action' => 'Recommended by:', 'name' => $signatories['coordinator'] ?? null, 'role' => 'ALP Coordinator'],
        ['action' => 'Noted by:', 'name' => null, 'role' => 'Assistant CID Chief for Student Affairs/DSA Chief'],
    ],
]])
@endsection
