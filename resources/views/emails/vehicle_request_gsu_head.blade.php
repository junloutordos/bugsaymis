@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#6366f1,#3b82f6)')
@section('header-title','Vehicle Request — GSU Head Action Required')
@section('header-subtitle','PSHS-CRC MIS — GSU Transport')

@section('content')
@php
    $dates = $request->date_needed_multiple ?? ($request->date_needed ? [\Carbon\Carbon::parse($request->date_needed)->toDateString()] : []);
@endphp

<p class="greeting">Hello <strong>{{ $request->gsu_head?->name ?? 'GSU Head' }}</strong>,</p>
<p class="lead">A vehicle request has been approved by the Division Chief and now requires your action (assign driver / decline).</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Control No.</td><td class="val"><strong>{{ $request->control_number ?? '#'.$request->id }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $request->user?->name ?? '—' }}<br><span style="font-size:12px;color:#64748b;">{{ $request->user?->position ?? '' }}</span></td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $request->purpose ?? '—' }}</td></tr>
    <tr><td class="lbl">Destination</td><td class="val">{{ $request->destination ?? '—' }}</td></tr>
    <tr><td class="lbl">Vehicle</td><td class="val">{{ $request->vehicle_type ?? '—' }}</td></tr>
    <tr><td class="lbl">Date(s) of Trip</td><td class="val">
        @if(!empty($dates))<ul style="margin:0;padding-left:16px">@foreach($dates as $d)<li>{{ \Carbon\Carbon::parse($d)->toDateString() }}</li>@endforeach</ul>
        @else — @endif
    </td></tr>
    <tr><td class="lbl">Departure / ETA</td><td class="val">{{ ($request->time_of_departure ?? '—') . ' — ' . ($request->eta ?? '—') }}</td></tr>
    <tr><td class="lbl">Passengers</td><td class="val">{{ $request->passengers ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Filed</td><td class="val">{{ $request->created_at?->toDateString() ?? '—' }}</td></tr>
</table>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ url('/vehicle-requests/'.$request->id) }}">View Request in System →</a>
@endsection

@section('footer-note')If you do not have permission to act on this request, you may ignore this email.@endsection
