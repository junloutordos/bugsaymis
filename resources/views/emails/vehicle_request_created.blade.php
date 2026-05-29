@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','Vehicle Request — Approval Needed')
@section('header-subtitle','PSHS-CRC MIS — GSU Transport')

@section('content')
<p class="greeting">Hello <strong>{{ $request->divisionChief?->name ?? 'Division Chief' }}</strong>,</p>
<p class="lead">A new vehicle request has been submitted and assigned to you for approval. Please act within <strong>24 hours</strong>.</p>

@php
    $dates = $request->date_needed_multiple ?? ($request->date_needed ? [\Carbon\Carbon::parse($request->date_needed)->toDateString()] : []);
@endphp

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $request->user?->name ?? '—' }}<br><span style="font-size:12px;color:#64748b;">{{ $request->user?->position ?? '' }}</span></td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $request->purpose }}</td></tr>
    <tr><td class="lbl">Destination</td><td class="val">{{ $request->destination ?? '—' }}</td></tr>
    <tr><td class="lbl">Date(s) Needed</td><td class="val">
        @if(!empty($dates))<ul style="margin:0;padding-left:16px">@foreach($dates as $d)<li>{{ \Carbon\Carbon::parse($d)->toDateString() }}</li>@endforeach</ul>
        @else —
        @endif
    </td></tr>
    <tr><td class="lbl">Departure / ETA</td><td class="val">{{ $request->time_of_departure ?? '—' }} — {{ $request->eta ?? '—' }}</td></tr>
    <tr><td class="lbl">Vehicle</td><td class="val">{{ $request->vehicle_type ?? '—' }}</td></tr>
    <tr><td class="lbl">Passengers</td><td class="val">{{ $request->passengers ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Filed</td><td class="val">{{ $request->created_at?->format('F j, Y g:i A') ?? '—' }}</td></tr>
</table>
@endsection

@section('actions')
@if(!empty($approveUrl))<a class="btn btn-green" href="{{ $approveUrl }}">Approve</a>@endif
@if(!empty($declineUrl))<a class="btn btn-red" href="{{ $declineUrl }}">Decline</a>@endif
@endsection

@section('fallback-links')
@if(!empty($approveUrl))<p>Approve: <a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>@endif
@if(!empty($declineUrl))<p>Decline: <a href="{{ $declineUrl }}">{{ $declineUrl }}</a></p>@endif
@endsection

@section('footer-note')If you do not have permission to approve this request, you may ignore this email.@endsection
