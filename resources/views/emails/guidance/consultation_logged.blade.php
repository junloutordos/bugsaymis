@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','Guidance Consultation — New Entry')
@section('header-subtitle','PSHS-CRC MIS — Guidance Office')

@section('content')
<p class="greeting">Hello,</p>
<p class="lead">A new guidance consultation was scheduled via the kiosk. Please log in to CRCMIS to review and assign a counsellor.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $consult->id }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $requestor->name ?? ($consult->requestor ?? '—') }}</td></tr>
    <tr><td class="lbl">Preferred Date / Time</td><td class="val">{{ $consult->date_time_preferred ?? '—' }}</td></tr>
    <tr><td class="lbl">Concern</td><td class="val">{{ $consult->concern ?? '—' }}</td></tr>
    <tr><td class="lbl">Details</td><td class="val">{{ $consult->description ?? '—' }}</td></tr>
</table>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ route('guidance.consultations.index') }}">View Consultations →</a>
@endsection

@section('footer-note')If you do not handle guidance consultations, you may ignore this email.@endsection
