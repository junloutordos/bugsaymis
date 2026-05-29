@extends('emails.layouts.base')

@section('header-gradient', str_contains(strtolower($status ?? ''), 'approved') ? 'linear-gradient(90deg,#059669,#10b981)' : 'linear-gradient(90deg,#dc2626,#ef4444)')
@section('header-title','Vehicle Request — {{ $status }}')
@section('header-subtitle','PSHS-CRC MIS — GSU Transport')

@section('content')
@php
    $dates = $request->date_needed_multiple ?? ($request->date_needed ? [\Carbon\Carbon::parse($request->date_needed)->toDateString()] : []);
@endphp

<p class="greeting">Hello <strong>{{ $request->user?->name ?? 'Requestor' }}</strong>,</p>
<p class="lead">Your vehicle request has been updated.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Control No.</td><td class="val"><strong>{{ $request->control_number ?? '#'.$request->id }}</strong></td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $request->purpose ?? '—' }}</td></tr>
    <tr><td class="lbl">Destination</td><td class="val">{{ $request->destination ?? '—' }}</td></tr>
    <tr><td class="lbl">Vehicle</td><td class="val">{{ $request->vehicle_type ?? '—' }}</td></tr>
    <tr><td class="lbl">Date(s) of Trip</td><td class="val">
        @if(!empty($dates))<ul style="margin:0;padding-left:16px">@foreach($dates as $d)<li>{{ \Carbon\Carbon::parse($d)->toDateString() }}</li>@endforeach</ul>
        @else — @endif
    </td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge {{ str_contains(strtolower($status ?? ''), 'approved') ? 'badge-green' : 'badge-red' }}">{{ $status }}</span></td></tr>
    @if(!empty($reason) && str_contains(strtolower($status ?? ''), 'declined'))
    <tr><td class="lbl">Reason</td><td class="val" style="color:#991b1b;">{{ $reason }}</td></tr>
    @endif
    @if(!empty($actor))
    <tr><td class="lbl">Actioned By</td><td class="val">{{ $actor }}</td></tr>
    @endif
</table>
@endsection
