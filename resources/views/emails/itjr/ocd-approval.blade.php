@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','IT Job Request — OCD Approval Required')
@section('header-subtitle','CRCMIS IT Services')

@section('content')
@php
    $priorityClass = match(strtolower($jobRequest->priority ?? 'normal')) {
        'urgent' => 'priority-urgent',
        'high'   => 'priority-high',
        'low'    => 'priority-low',
        default  => 'priority-normal',
    };
    $isEventRelated = !empty($jobRequest->event_date) || strtolower($jobRequest->category ?? '') === 'technical assistance for events';
@endphp

<p class="greeting">Hello <strong>{{ $ocdApprover?->name ?? 'OCD Approver' }}</strong>,</p>
<p class="lead">IT Job Request <strong>{{ $jobRequest->itjr_no }}</strong> has been approved by the Division Chief and now requires your final approval before the MIS team begins processing.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">ITJR No.</td><td class="val"><strong style="font-family:monospace;">{{ $jobRequest->itjr_no }}</strong></td></tr>
    <tr><td class="lbl">Title</td><td class="val">{{ $jobRequest->title }}</td></tr>
    <tr><td class="lbl">Submitted By</td><td class="val">{{ $jobRequest->user?->name ?? '—' }}<br><span style="font-size:12px;color:#64748b;">{{ $jobRequest->user?->position ?? '' }}</span></td></tr>
    <tr><td class="lbl">Category</td><td class="val">{{ $jobRequest->category }}</td></tr>
    <tr><td class="lbl">Priority</td><td class="val"><span class="badge {{ $priorityClass }}">{{ ucfirst($jobRequest->priority ?? 'Normal') }}</span></td></tr>
    @if($jobRequest->event_date)
    <tr><td class="lbl">Event Date</td><td class="val"><strong style="color:#dc2626;">{{ \Carbon\Carbon::parse($jobRequest->event_date)->format('F j, Y') }}</strong></td></tr>
    @endif
    @if($jobRequest->expected_completion_date)
    <tr><td class="lbl">Expected By</td><td class="val">{{ \Carbon\Carbon::parse($jobRequest->expected_completion_date)->format('F j, Y') }}</td></tr>
    @endif
    <tr><td class="lbl">DC Approved On</td><td class="val">{{ $jobRequest->dc_approval_date?->format('F j, Y') ?? '—' }}</td></tr>
    <tr><td class="lbl">DC Approver</td><td class="val">{{ $jobRequest->divisionchief?->name ?? '—' }}</td></tr>
</table>

@if($jobRequest->description)
<div class="callout callout-blue" style="margin-top:12px;">
    <div class="callout-title">Issue Description</div>
    {{ $jobRequest->description }}
</div>
@endif

@if($jobRequest->mis_assessment)
<div class="callout callout-blue">
    <div class="callout-title">MIS Assessment</div>
    {{ $jobRequest->mis_assessment }}
</div>
@endif

@if($isEventRelated)
<div class="callout callout-amber">
    <div class="callout-title">⚠ Event-Related Request</div>
    This is a Technical Assistance for Events request. Timely approval is needed to allow the MIS team sufficient preparation time.
</div>
@endif

<div class="callout callout-blue">
    <div class="callout-title">Action Required</div>
    Click <strong>Approve</strong> to authorize the MIS team to begin processing this request, or <strong>Decline</strong> with a reason. Please act within 24 hours.
</div>
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
@if(!empty($approveUrl))
<p>Approve: <a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>
@endif
@if(!empty($declineUrl))
<p>Decline: <a href="{{ $declineUrl }}">{{ $declineUrl }}</a></p>
@endif
@endsection

@section('footer-note')
If you do not have permission to approve this request, you may ignore this email.
@endsection
