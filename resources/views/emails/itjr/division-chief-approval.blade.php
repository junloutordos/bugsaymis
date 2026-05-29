@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','IT Job Request — DC Approval Required')
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

<p class="greeting">Hello <strong>{{ $jobRequest->divisionchief?->name ?? 'Division Chief' }}</strong>,</p>
<p class="lead">A new IT Job Request has been submitted by an employee under your division and requires your approval before it can be processed by the MIS team.</p>

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
    <tr><td class="lbl">Date Filed</td><td class="val">{{ $jobRequest->created_at->format('F j, Y, g:i A') }}</td></tr>
</table>

@if($jobRequest->description)
<div class="callout callout-blue" style="margin-top:12px;">
    <div class="callout-title">Issue Description</div>
    {{ $jobRequest->description }}
</div>
@endif

@if($isEventRelated)
<div class="callout callout-amber">
    <div class="callout-title">⚠ 3-Day Filing Rule</div>
    This request is related to a Technical Assistance for Events and must be filed <strong>at least 3 working days before the event</strong>. Please approve promptly to ensure MIS can prepare in time.
</div>
@endif

<div class="callout callout-blue">
    <div class="callout-title">Action Required</div>
    Click <strong>Approve</strong> to endorse this request to OCD for final approval, or <strong>Decline</strong> if the request does not meet requirements. Please act within 24 hours.
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
