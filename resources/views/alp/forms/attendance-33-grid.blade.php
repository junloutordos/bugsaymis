@extends('alp.forms._layout')
@php
    // The physical form reserves room for 8 date columns per printed page —
    // chunk sessions the same way so a month with more meeting dates just
    // continues onto additional pages instead of overflowing one page.
    $sessionChunks = $sessions->chunk(8);
    $statusCode = fn (?string $status) => match ($status) {
        'present' => 'P', 'absent' => 'A', 'tardy' => 'T', 'cutting' => 'C', 'excused' => 'E', default => '',
    };
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'ATTENDANCE FORM',
    'periodLine' => 'Quarter, S.Y. '.$cycle->schoolYear->name,
    'extraLines' => ['ALP: '.$cycle->program->name, 'Month: '.$month->format('F Y')],
])

@if($sessions->isEmpty())
    <p>No attendance dates recorded for {{ $month->format('F Y') }}.</p>
@endif

@foreach($sessionChunks as $chunkIndex => $chunk)
    @if($chunkIndex > 0)<div class="page-break"></div>@endif
    <table class="bordered">
        <thead>
            <tr>
                <th rowspan="2" style="width:5%;white-space:nowrap">#</th>
                <th rowspan="2">Names:</th>
                <th rowspan="2" style="width:8%">Sex</th>
                <th colspan="{{ $chunk->count() }}">Date:</th>
            </tr>
            <tr>
                @foreach($chunk as $session)<th style="width:{{ (int) floor(60 / max($chunk->count(),1)) }}%">{{ $session->session_date->format('n/j') }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
        @foreach($members as $member)
            <tr>
                <td style="white-space:nowrap">{{ $loop->iteration }}.</td>
                <td>{{ $member->student->full_name }}</td>
                <td>{{ $member->student->sex }}</td>
                @foreach($chunk as $session)
                    <td style="text-align:center">{{ $statusCode(data_get($records, "{$member->id}.{$session->id}.status")) }}</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
@endforeach

<p style="font-size:8pt;color:#334155;margin-top:4px">P = Present &nbsp; A = Absent &nbsp; T = Tardy &nbsp; C = Cutting &nbsp; E = Excused</p>

@include('alp.forms._signatures', ['rows' => [
    [
        ['action' => 'Prepared by:', 'name' => $signatories['adviser'] ?? null, 'role' => 'ALP Adviser'],
        ['action' => 'Noted by:', 'name' => $signatories['coordinator'] ?? null, 'role' => 'ALP Coordinator'],
    ],
]])
@endsection
