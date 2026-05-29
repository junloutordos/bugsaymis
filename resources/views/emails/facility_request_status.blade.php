@extends('emails.layouts.base')

@section('header-gradient', str_contains(strtolower($status ?? ''), 'approved') ? 'linear-gradient(90deg,#059669,#10b981)' : 'linear-gradient(90deg,#dc2626,#ef4444)')
@section('header-title','Facility Request — {{ $status }}')
@section('header-subtitle','PSHS-CRC MIS — Facility Management')

@section('content')
<p class="greeting">Hello <strong>{{ $request->requestor ?? 'Requestor' }}</strong>,</p>
@if(!empty($approver) && str_contains(strtolower($status ?? ''), 'approved'))
<p class="lead">Your facility request has been <strong>approved</strong> by {{ $approver }}.</p>
@elseif(!empty($approver) && str_contains(strtolower($status ?? ''), 'declined'))
<p class="lead">Your facility request has been <strong>declined</strong> by {{ $approver }}.</p>
@else
<p class="lead">Your facility request status has been updated.</p>
@endif

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Activity / Purpose</td><td class="val">{{ $request->activity }}{{ $request->purpose ? ' — ' . $request->purpose : '' }}</td></tr>
    <tr><td class="lbl">Venue</td><td class="val">@if(is_array($request->venue)) {{ implode(', ', $request->venue) }} @else {{ $request->venue ?? '—' }} @endif</td></tr>
    <tr><td class="lbl">Date(s)</td><td class="val">
        @if(!empty($request->date_start))
            {{ \Carbon\Carbon::parse($request->date_start)->toDateString() }}
            @if(!empty($request->date_end)) — {{ \Carbon\Carbon::parse($request->date_end)->toDateString() }} @endif
        @else — @endif
    </td></tr>
    <tr><td class="lbl">Time(s)</td><td class="val">{{ $request->time_start ?? '—' }}{{ $request->time_end ? ' — ' . $request->time_end : '' }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge {{ str_contains(strtolower($status ?? ''), 'approved') ? 'badge-green' : 'badge-red' }}">{{ $status }}</span></td></tr>
    @if(!empty($reason) && str_contains(strtolower($status ?? ''), 'declined'))
    <tr><td class="lbl">Reason</td><td class="val" style="color:#991b1b;">{{ $reason }}</td></tr>
    @endif
</table>
@endsection
