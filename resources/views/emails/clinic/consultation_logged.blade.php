@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','Clinic Consultation — New Entry')
@section('header-subtitle','PSHS-CRC MIS — School Clinic')

@section('content')
<p class="greeting">Hello Nurse,</p>
<p class="lead">A new clinic consultation was logged via the kiosk. Please log in to CRCMIS to review.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $consult->id }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $requestor->name ?? ($consult->requestor ?? '—') }}</td></tr>
    <tr><td class="lbl">Sex</td><td class="val">{{ $requestor->sex ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Scheduled</td><td class="val">{{ $dateScheduled ?? '—' }}</td></tr>
    @if(!empty($requestor->grade_level) || !empty($requestor->section))
    <tr><td class="lbl">Grade Level</td><td class="val">{{ $requestor->grade_level ?? '—' }}</td></tr>
    <tr><td class="lbl">Section</td><td class="val">{{ $requestor->section ?? '—' }}</td></tr>
    @else
    <tr><td class="lbl">Unit / Office</td><td class="val">{{ $requestor->office ?? ($consult->unit ?? '—') }}</td></tr>
    @endif
    <tr><td class="lbl">Reason / Concern</td><td class="val">{{ $consult->reason ?? '—' }}</td></tr>
</table>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ route('consultations.index') }}">View Consultations →</a>
@endsection

@section('footer-note')If you do not handle clinic consultations, you may ignore this email.@endsection
