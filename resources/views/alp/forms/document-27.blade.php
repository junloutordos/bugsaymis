@extends('alp.forms._layout')
@php
    $officers = $cycle->officers->sortBy('sort_order');
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'OFFICIAL LIST OF OFFICERS',
    'periodLine' => 'S.Y. '.$cycle->schoolYear->name,
])

<p class="field-line">ALP: <span class="fill">{{ $cycle->program->name }}</span></p>
<p>The following is the list of scholars who will serve as the official officers:</p>

<table class="bordered">
    <thead><tr><th>Names</th><th>Sex</th><th>Grade &amp; Section</th><th>Position</th></tr></thead>
    <tbody>
    @foreach($officers as $officer)
        <tr>
            <td>{{ $officer->membership->student->full_name }}</td>
            <td>{{ $officer->membership->student->sex }}</td>
            <td>{{ $officer->membership->enrollment?->grade_level }} - {{ $officer->membership->enrollment?->section?->sectionname }}</td>
            <td>{{ $officer->position }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@include('alp.forms._signatures', ['rows' => [
    [
        ['action' => 'Prepared by:', 'name' => $signatories['adviser'] ?? null, 'role' => 'ALP Adviser'],
        ['action' => 'Noted by:', 'name' => $signatories['coordinator'] ?? null, 'role' => 'ALP Coordinator'],
    ],
    [
        ['action' => 'Recommended by:', 'name' => null, 'role' => 'Assistant CID Chief for Student Affairs/DSA Chief'],
        ['action' => 'Approved by:', 'name' => null, 'role' => 'Campus Director'],
    ],
]])
@endsection
