@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0891b2,#06b6d4)')
@section('header-title','Follow-up Consultation Scheduled')
@section('header-subtitle','PSHS-CRC MIS — Guidance Office')

@section('content')
<p class="greeting">Dear <strong>{{ $studentName ?? 'Student' }}</strong>,</p>
<p class="lead">A follow-up guidance consultation has been scheduled for you. Please see the details below.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Student Name</td><td class="val"><strong>{{ $studentName ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Reason / Concern</td><td class="val">{{ $consult->concern ?? ($consult->description ?? '—') }}</td></tr>
    <tr><td class="lbl">Follow-up Date & Time</td><td class="val"><strong>{{ isset($followupDate) ? \Carbon\Carbon::parse($followupDate)->toDayDateTimeString() : (optional($consult->followup_date)->toDayDateTimeString() ?? '—') }}</strong></td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">Reminder</div>
    Please arrive on time. If you need to reschedule, contact the Guidance Office as early as possible.
</div>
@endsection
