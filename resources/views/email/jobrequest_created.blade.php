@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','New IT Job Request Submitted')
@section('header-subtitle','CRCMIS IT Services')

@section('content')
@php
    $priorityClass = match(strtolower($jobRequest->priority ?? 'normal')) {
        'urgent' => 'priority-urgent',
        'high'   => 'priority-high',
        'low'    => 'priority-low',
        default  => 'priority-normal',
    };
@endphp

<p class="greeting">Hello MIS Team,</p>
<p class="lead">A new IT Job Request has been submitted and is pending DC and OCD approval before it enters the queue.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">ITJR No.</td><td class="val"><strong style="font-family:monospace;">{{ $jobRequest->itjr_no }}</strong></td></tr>
    <tr><td class="lbl">Title</td><td class="val">{{ $jobRequest->title }}</td></tr>
    <tr><td class="lbl">Filed By</td><td class="val">{{ $jobRequest->user?->name ?? '—' }}<br><span style="font-size:12px;color:#64748b;">{{ $jobRequest->user?->position ?? '' }}</span></td></tr>
    <tr><td class="lbl">Category</td><td class="val">{{ $jobRequest->category }}</td></tr>
    <tr><td class="lbl">Priority</td><td class="val"><span class="badge {{ $priorityClass }}">{{ ucfirst($jobRequest->priority ?? 'Normal') }}</span></td></tr>
    @if($jobRequest->event_date)
    <tr><td class="lbl">Event Date</td><td class="val"><strong style="color:#dc2626;">{{ \Carbon\Carbon::parse($jobRequest->event_date)->format('F j, Y') }}</strong></td></tr>
    @endif
    @if($jobRequest->expected_completion_date)
    <tr><td class="lbl">Expected By</td><td class="val">{{ \Carbon\Carbon::parse($jobRequest->expected_completion_date)->format('F j, Y') }}</td></tr>
    @endif
    <tr><td class="lbl">Date Filed</td><td class="val">{{ $jobRequest->created_at->format('F j, Y, g:i A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-slate">{{ $jobRequest->status }}</span></td></tr>
</table>

@if($jobRequest->description)
<div class="callout callout-blue">
    <div class="callout-title">Issue Description</div>
    {{ $jobRequest->description }}
</div>
@endif
@endsection
