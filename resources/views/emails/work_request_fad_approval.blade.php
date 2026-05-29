@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#7c3aed,#8b5cf6)')
@section('header-title','Work Request — FAD Approval Needed')
@section('header-subtitle','PSHS-CRC MIS — GSU Work Requests')

@section('content')
<p class="greeting">Hello <strong>{{ $chief?->name ?? $request->divisionChief?->name ?? 'FAD Chief' }}</strong>,</p>
<p class="lead">A work request has been assigned and requires your FAD approval to proceed.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Issue</td><td class="val">{{ $request->issue ?? '—' }}</td></tr>
    <tr><td class="lbl">Description</td><td class="val">{{ $request->description ?? '—' }}</td></tr>
    <tr><td class="lbl">Priority</td><td class="val"><span class="badge priority-{{ strtolower($request->priority ?? 'normal') }}">{{ ucfirst($request->priority ?? 'Normal') }}</span></td></tr>
    <tr><td class="lbl">Location</td><td class="val">{{ $request->division?->name ?? ($request->location_division_id ?? '—') }}{{ $request->office?->name ? ' / ' . $request->office->name : '' }}</td></tr>
    <tr><td class="lbl">Expected Completion</td><td class="val">{{ $request->expected_completion_date ? \Carbon\Carbon::parse($request->expected_completion_date)->toDateString() : '—' }}</td></tr>
</table>
@endsection

@section('actions')
@if(!empty($approveUrl))<a class="btn btn-green" href="{{ $approveUrl }}">Approve</a>@endif
@if(!empty($declineUrl))<a class="btn btn-red" href="{{ $declineUrl }}">Decline</a>@endif
@endsection

@section('fallback-links')
@if(!empty($approveUrl))<p>Approve: <a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>@endif
@endsection

@section('footer-note')If you do not have permission to approve this request, you may ignore this email.@endsection
