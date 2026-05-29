@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#6366f1,#3b82f6)')
@section('header-title','Facility Request — GSU Action Required')
@section('header-subtitle','PSHS-CRC MIS — Facility Management')

@section('content')
<p class="greeting">Hello,</p>
<p class="lead">A facility request approved by the Division Chief requires your action as GSU Head. Please process within <strong>24 hours</strong>.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $request->requestor ?? ($request->email ?? '—') }}</td></tr>
    <tr><td class="lbl">Unit / Office</td><td class="val">{{ $request->unit ?? '—' }}</td></tr>
    <tr><td class="lbl">Activity / Purpose</td><td class="val">{{ $request->activity }}{{ $request->purpose ? ' — ' . $request->purpose : '' }}</td></tr>
    <tr><td class="lbl">Date(s)</td><td class="val">
        @if(!empty($request->date_start))
            {{ \Carbon\Carbon::parse($request->date_start)->toDateString() }}
            @if(!empty($request->date_end)) — {{ \Carbon\Carbon::parse($request->date_end)->toDateString() }} @endif
        @else — @endif
    </td></tr>
    <tr><td class="lbl">Time(s)</td><td class="val">{{ $request->time_start ?? '—' }}{{ $request->time_end ? ' — ' . $request->time_end : '' }}</td></tr>
    <tr><td class="lbl">Venue</td><td class="val">{{ $venueDisplay ?? (is_array($request->venue) ? implode(', ', $request->venue) : ($request->venue ?? '—')) }}</td></tr>
    <tr><td class="lbl">Equipment</td><td class="val">
        @php
            $equip = $request->equipment ?? [];
            if (!is_array($equip) && $equip) $equip = [$equip];
            $qtys = $request->equipment_quantities ?? [];
            if (!is_array($qtys)) $qtys = $qtys ? json_decode($qtys, true) ?? [] : [];
            $lines = [];
            foreach ($equip as $e) { $q = $qtys[$e] ?? null; $lines[] = $q ? "$e (".intval($q).")" : $e; }
        @endphp
        {{ count($lines) ? implode(', ', $lines) : ($request->others ?? '—') }}
    </td></tr>
    <tr><td class="lbl">Participants</td><td class="val">{{ $request->participants ?? '—' }}</td></tr>
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

@section('footer-note')If you do not have permission to act on this request, you may ignore this email.@endsection
