@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#059669,#10b981)')
@section('header-title','Consultation Appointment Scheduled')
@section('header-subtitle','PSHS-CRC MIS — School Clinic')

@section('content')
<p class="greeting">Hello <strong>{{ $consult->requestor ?? ($requestor->name ?? 'Requestor') }}</strong>,</p>
<p class="lead">Your consultation appointment has been scheduled. Please come to the clinic at the time indicated below.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Scheduled Date & Time</td><td class="val"><strong>{{ $dateScheduled ?? '—' }}</strong></td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">Reminder</div>
    Please come on time. If you need to reschedule, contact the school clinic in advance.
</div>
@endsection
