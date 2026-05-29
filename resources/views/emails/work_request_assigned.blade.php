@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#7c3aed,#8b5cf6)')
@section('header-title','Work Request Assigned to You')
@section('header-subtitle','PSHS-CRC MIS — GSU Work Requests')

@section('content')
<p class="greeting">Hello <strong>{{ $request->assignedUser?->name ?? 'Staff' }}</strong>,</p>
<p class="lead">A work request has been assigned to you. Please review the details and attend to it promptly.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Issue</td><td class="val">{{ $request->issue ?? '—' }}</td></tr>
    <tr><td class="lbl">Description</td><td class="val">{{ $request->description ?? '—' }}</td></tr>
    <tr><td class="lbl">Priority</td><td class="val"><span class="badge priority-{{ strtolower($request->priority ?? 'normal') }}">{{ ucfirst($request->priority ?? 'Normal') }}</span></td></tr>
    <tr><td class="lbl">Location</td><td class="val">{{ $request->division?->name ?? ($request->location_division_id ?? '—') }}{{ $request->office?->name ? ' / ' . $request->office->name : '' }}</td></tr>
    <tr><td class="lbl">Expected Completion</td><td class="val">{{ $request->expected_completion_date ? \Carbon\Carbon::parse($request->expected_completion_date)->toDateString() : '—' }}</td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $request->requester?->name ?? '—' }}</td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">Action Required</div>
    Log in to CRCMIS, open the work request, and update its status as you work on it. Mark as Completed once resolved.
</div>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ route('work-requests.index') }}">Open Work Requests →</a>
@endsection
