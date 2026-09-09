@extends('alp.forms._layout')
@php
    $members = $cycle->memberships->where('status', 'active');
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'OFFICIAL CLASS LIST',
    'periodLine' => 'S.Y. '.$cycle->schoolYear->name,
])

<p class="field-line">Alternative Learning Program: <span class="fill">{{ $cycle->program->name }}</span></p>

<table class="bordered">
    <thead><tr><th style="width:6%">#</th><th>Name/s</th><th style="width:12%">Sex</th><th>Grade and Section</th><th>Signature/s</th></tr></thead>
    <tbody>
    @foreach($members as $member)
        <tr>
            <td style="white-space:nowrap">{{ $loop->iteration }}.</td>
            <td>{{ $member->student->full_name }}</td>
            <td>{{ $member->student->sex }}</td>
            <td>{{ $member->enrollment?->grade_level }} - {{ $member->enrollment?->section?->sectionname }}</td>
            <td>&nbsp;</td>
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
