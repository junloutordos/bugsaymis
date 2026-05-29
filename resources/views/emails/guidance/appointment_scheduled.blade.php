@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#059669,#10b981)')
@section('header-title','Guidance Consultation Appointment')
@section('header-subtitle','PSHS-CRC MIS — Guidance Office')

@section('content')
<p class="greeting">Dear <strong>{{ $studentName ?? 'Student' }}</strong>,</p>
<p class="lead">This is your official appointment schedule assigned by the Guidance Office. Please come at the designated time.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Student Name</td><td class="val"><strong>{{ $studentName ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Reason / Concern</td><td class="val">{{ $consult->concern ?? ($consult->description ?? '—') }}</td></tr>
    <tr><td class="lbl">Appointment Date & Time</td><td class="val"><strong>{{ optional($consult->date_time_assigned)->toDayDateTimeString() ?? $consult->date_time_assigned ?? '—' }}</strong></td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">Reminder</div>
    Please arrive on time. If you need to reschedule, contact the Guidance Office as early as possible.
</div>
@endsection
