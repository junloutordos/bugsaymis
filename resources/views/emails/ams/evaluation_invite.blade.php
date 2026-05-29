@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#4f46e5,#6366f1)')
@section('header-title','Activity Evaluation Invitation')
@section('header-subtitle','PSHS-CRC MIS — Activity Management System')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">Thank you for participating in the following activity. Please take a moment to evaluate it — your feedback matters!</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Activity</td><td class="val"><strong>{{ $activity->title }}</strong></td></tr>
    <tr><td class="lbl">Date</td><td class="val">
        {{ \Carbon\Carbon::parse($activity->start_date)->format('F d, Y') }}
        @if($activity->end_date && $activity->end_date != $activity->start_date)
         – {{ \Carbon\Carbon::parse($activity->end_date)->format('F d, Y') }}
        @endif
    </td></tr>
    @if($activity->venue)
    <tr><td class="lbl">Venue</td><td class="val">{{ $activity->venue }}</td></tr>
    @endif
</table>

<div class="callout callout-blue">
    <div class="callout-title">Important</div>
    Your certificate of participation will only be released after you complete the evaluation. The link below is unique to you — please do not share it.
</div>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ $evaluationUrl }}">Evaluate This Activity →</a>
@endsection
