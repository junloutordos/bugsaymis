@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#6366f1,#4f46e5)')
@section('header-title','You\'re Invited!')
@section('header-subtitle','PSHS-CRC MIS — Activity Management System')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">You are cordially invited to participate in the following activity:</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Activity</td><td class="val"><strong>{{ $activity->title }}</strong></td></tr>
    <tr><td class="lbl">Date</td><td class="val">
        {{ \Carbon\Carbon::parse($activity->start_date)->format('F d, Y') }}
        @if($activity->end_date && $activity->end_date != $activity->start_date)
         – {{ \Carbon\Carbon::parse($activity->end_date)->format('F d, Y') }}
        @endif
    </td></tr>
    @if($activity->start_time || $activity->end_time)
    <tr><td class="lbl">Time</td><td class="val">
        @if($activity->start_time){{ \Carbon\Carbon::parse($activity->start_time)->format('g:i A') }}@endif
        @if($activity->start_time && $activity->end_time) – @endif
        @if($activity->end_time){{ \Carbon\Carbon::parse($activity->end_time)->format('g:i A') }}@endif
    </td></tr>
    @endif
    @if($activity->venue)
    <tr><td class="lbl">Venue</td><td class="val">{{ $activity->venue }}</td></tr>
    @endif
    @if($activity->resource_person)
    <tr><td class="lbl">Resource Person</td><td class="val">{{ $activity->resource_person }}</td></tr>
    @endif
    @if($activity->total_hours)
    <tr><td class="lbl">Total Hours</td><td class="val">{{ $activity->total_hours }}</td></tr>
    @endif
</table>

<div class="callout callout-blue">
    Please check attachments for the activity banner and Special Order (if available).
</div>
@endsection
