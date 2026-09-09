@extends('alp.forms._layout')
@php
    $officers = $cycle->officers->sortBy('sort_order');
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'CERTIFICATION OF STUDENT RECORD',
    'periodLine' => 'S.Y. '.$cycle->schoolYear->name,
])

<p>This is to certify that the following ALP student officers are in good academic standing and fit to lead the Alternative Learning Program {{ $cycle->program->name }}.</p>

<table class="bordered">
    <thead><tr><th>Name/s</th><th>Male/Female</th><th>Grade &amp; Section</th><th>ALP Position</th><th>Remarks</th></tr></thead>
    <tbody>
    @foreach($officers as $officer)
        <tr>
            <td>{{ $officer->membership->student->full_name }}</td>
            <td>{{ $officer->membership->student->sex }}</td>
            <td>{{ $officer->membership->enrollment?->grade_level }} - {{ $officer->membership->enrollment?->section?->sectionname }}</td>
            <td>{{ $officer->position }}</td>
            <td>{{ data_get($officer->academic_standing_snapshot, 'standing', ucfirst($officer->registrar_status)) }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@include('alp.forms._signatures', ['rows' => [
    [[
        'action' => 'Signed by:',
        'name' => $signatories['registrar'] ?? null,
        'role' => 'Campus Registrar',
        'date' => $signatories['registrar_certified_at'] ?? true,
    ]],
]])
@endsection
