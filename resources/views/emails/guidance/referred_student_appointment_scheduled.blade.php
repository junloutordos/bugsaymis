@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0891b2,#06b6d4)')
@section('header-title','Guidance Consultation Appointment')
@section('header-subtitle','PSHS-CRC MIS — Guidance Office')

@section('content')
<p class="greeting">Dear <strong>{{ $studentName ?? 'Student' }}</strong>,</p>
<p class="lead">The Guidance Office would like to meet with you for a consultation. This is part of supporting your well-being and growth — please don't hesitate to attend.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Student Name</td><td class="val"><strong>{{ $studentName ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Consultation Date & Time</td><td class="val"><strong>{{ optional($consult->date_time_assigned)->toDayDateTimeString() ?? $consult->date_time_assigned ?? '—' }}</strong></td></tr>
</table>

<div class="callout callout-blue">
    If you have questions, you may reach out to the Guidance Office directly.
</div>
@endsection
