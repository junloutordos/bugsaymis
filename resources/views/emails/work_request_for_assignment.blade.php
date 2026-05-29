@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#7c3aed,#8b5cf6)')
@section('header-title','Work Request — Staff Assignment Needed')
@section('header-subtitle','PSHS-CRC MIS — GSU Work Requests')

@section('content')
<p class="greeting">Hello GSU Head,</p>
<p class="lead">A work request has been approved and needs to be assigned to a staff member for resolution.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Issue</td><td class="val">{{ $request->issue ?? '—' }}</td></tr>
    <tr><td class="lbl">Description</td><td class="val">{{ $request->description ?? '—' }}</td></tr>
    <tr><td class="lbl">Priority</td><td class="val"><span class="badge priority-{{ strtolower($request->priority ?? 'normal') }}">{{ ucfirst($request->priority ?? 'Normal') }}</span></td></tr>
    <tr><td class="lbl">Location</td><td class="val">{{ $request->division?->name ?? ($request->location_division_id ?? '—') }}{{ $request->office?->name ? ' / ' . $request->office->name : '' }}</td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $request->requester?->name ?? '—' }}</td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">Action Required</div>
    Open the work request in CRCMIS and assign it to the appropriate staff member.
</div>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ route('work-requests.index') }}">Open Work Requests →</a>
@endsection

@section('footer-note')If you do not have permission to assign this request, you may ignore this email.@endsection
