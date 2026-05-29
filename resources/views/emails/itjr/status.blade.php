@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','IT Job Request — Status Update')
@section('header-subtitle','CRCMIS IT Services')

@section('content')
@php
    $isDeclined = in_array(strtolower($status ?? ''), ['declined', 'rejected']);
    $isApproved = str_contains(strtolower($status ?? ''), 'approved');
    $isCompleted = in_array(strtolower($status ?? ''), ['completed', 'done', 'closed']);
    $isInProgress = str_contains(strtolower($status ?? ''), 'progress') || str_contains(strtolower($status ?? ''), 'assigned');
    $badgeClass = $isDeclined ? 'badge-red' : ($isApproved ? 'badge-blue' : ($isCompleted ? 'badge-green' : ($isInProgress ? 'badge-cyan' : 'badge-slate')));
@endphp

<p class="greeting">Hello <strong>{{ $jobRequest->user?->name ?? 'User' }}</strong>,</p>
<p class="lead">Your IT Job Request has been updated. Please see the details below.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">ITJR No.</td><td class="val"><strong style="font-family:monospace;">{{ $jobRequest->itjr_no }}</strong></td></tr>
    <tr><td class="lbl">Title</td><td class="val">{{ $jobRequest->title }}</td></tr>
    <tr><td class="lbl">Category</td><td class="val">{{ $jobRequest->category }}</td></tr>
    <tr><td class="lbl">Priority</td><td class="val"><span class="badge priority-{{ strtolower($jobRequest->priority ?? 'normal') }}">{{ ucfirst($jobRequest->priority ?? 'Normal') }}</span></td></tr>
    <tr><td class="lbl">New Status</td><td class="val"><span class="badge {{ $badgeClass }}">{{ $status }}</span></td></tr>
    @if(!empty($approverRole))
    <tr><td class="lbl">Actioned By</td><td class="val">{{ $approverRole }}</td></tr>
    @endif
    @if($jobRequest->expected_completion_date && $isInProgress)
    <tr><td class="lbl">Expected Completion</td><td class="val"><strong>{{ \Carbon\Carbon::parse($jobRequest->expected_completion_date)->format('F j, Y') }}</strong></td></tr>
    @endif
    @if($jobRequest->assignedTo?->name && $isInProgress)
    <tr><td class="lbl">Assigned To</td><td class="val">{{ $jobRequest->assignedTo->name }}</td></tr>
    @endif
    @if($jobRequest->completed_at && $isCompleted)
    <tr><td class="lbl">Completed On</td><td class="val">{{ $jobRequest->completed_at->format('F j, Y, g:i A') }}</td></tr>
    @endif
</table>

@if(!empty($reason))
<div class="callout {{ $isDeclined ? 'callout-red' : 'callout-amber' }}">
    <div class="callout-title">{{ $isDeclined ? 'Reason for Decline' : 'Remarks' }}</div>
    {{ $reason }}
</div>
@endif

@if($isDeclined)
<div class="callout callout-blue">
    <div class="callout-title">What to do next</div>
    If you believe this request was declined in error, or if the issue still needs to be resolved, please file a new IT Job Request in CRCMIS with more details.
</div>
@elseif($isCompleted)
<div class="callout callout-green">
    <div class="callout-title">Request completed</div>
    If the issue has not been fully resolved, please file a follow-up IT Job Request in CRCMIS.
</div>
@elseif($isInProgress)
<div class="callout callout-blue">
    <div class="callout-title">In progress</div>
    The MIS team is now working on your request. You will receive another notification when it is completed.
</div>
@endif
@endsection
