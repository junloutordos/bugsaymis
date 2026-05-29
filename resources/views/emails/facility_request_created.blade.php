@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','Facility Request — Approval Needed')
@section('header-subtitle','PSHS-CRC MIS — Facility Management')

@section('content')
<p class="greeting">Hello <strong>{{ $request->unit ?? 'Division Chief' }}</strong>,</p>
<p class="lead">A new facility request has been submitted and assigned to you for review and approval. Please act within <strong>24 hours</strong>.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $request->requestor ?? '—' }}</td></tr>
    <tr><td class="lbl">Activity / Purpose</td><td class="val">{{ $request->activity }}{{ $request->purpose ? ' — ' . $request->purpose : '' }}</td></tr>
    <tr><td class="lbl">Date(s)</td><td class="val">
        @if(!empty($request->date_start))
            {{ \Carbon\Carbon::parse($request->date_start)->toDateString() }}
            @if(!empty($request->date_end)) — {{ \Carbon\Carbon::parse($request->date_end)->toDateString() }} @endif
        @else —
        @endif
    </td></tr>
    <tr><td class="lbl">Time(s)</td><td class="val">{{ $request->time_start ?? '—' }}{{ $request->time_end ? ' — ' . $request->time_end : '' }}</td></tr>
    <tr><td class="lbl">Venue</td><td class="val">{{ $venueDisplay ?? (is_array($request->venue) ? implode(', ', $request->venue) : ($request->venue ?? '—')) }}</td></tr>
    <tr><td class="lbl">Equipment</td><td class="val">@if(is_array($request->equipment)) {{ implode(', ', $request->equipment) }} @else {{ $request->equipment ?? ($request->others ?? '—') }} @endif</td></tr>
    <tr><td class="lbl">Participants</td><td class="val">{{ $request->participants ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Filed</td><td class="val">{{ $request->created_at?->format('F j, Y g:i A') ?? now()->format('F j, Y') }}</td></tr>
</table>
@endsection

@section('actions')
@if(!empty($approveUrl))
<a class="btn btn-green" href="{{ $approveUrl }}">Approve</a>
@endif
@if(!empty($declineUrl))
<a class="btn btn-red" href="{{ $declineUrl }}">Decline</a>
@endif
@endsection

@section('fallback-links')
@if(!empty($approveUrl))<p>Approve: <a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>@endif
@if(!empty($declineUrl))<p>Decline: <a href="{{ $declineUrl }}">{{ $declineUrl }}</a></p>@endif
@endsection

@section('footer-note')If you do not have permission to approve this request, you may ignore this email.@endsection
