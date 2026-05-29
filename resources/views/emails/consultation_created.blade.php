@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0891b2,#06b6d4)')
@section('header-title','New Consultation Request')
@section('header-subtitle','PSHS-CRC MIS — School Clinic')

@section('content')
<p class="greeting">Hello Nurse,</p>
<p class="lead">A new consultation request was submitted and needs scheduling. Please log in to CRCMIS to schedule the appointment.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $consult->id }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $requestor->name ?? ($consult->requestor ?? '—') }}</td></tr>
    <tr><td class="lbl">Sex</td><td class="val">{{ $requestor->sex ?? '—' }}</td></tr>
    @if(!empty($requestor->grade_level) || !empty($requestor->section))
    <tr><td class="lbl">Grade Level</td><td class="val">{{ $requestor->grade_level ?? '—' }}</td></tr>
    <tr><td class="lbl">Section</td><td class="val">{{ $requestor->section ?? '—' }}</td></tr>
    @else
    <tr><td class="lbl">Unit / Office</td><td class="val">{{ $requestor->office ?? ($consult->unit ?? '—') }}</td></tr>
    @endif
    <tr><td class="lbl">Date Scheduled</td><td class="val">{{ $dateScheduled ?? '—' }}</td></tr>
    <tr><td class="lbl">Reason</td><td class="val">{{ $consult->reason ?? '—' }}</td></tr>
</table>
@endsection

@section('footer-note')If you do not handle consultations, you may ignore this email.@endsection
