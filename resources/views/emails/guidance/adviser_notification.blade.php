@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0891b2,#06b6d4)')
@section('header-title','Guidance Consultation — Adviser Notification')
@section('header-subtitle','PSHS-CRC MIS — Guidance Office')

@section('content')
<p class="greeting">Dear <strong>{{ $adviserName }}</strong>,</p>
<p class="lead">This is to inform you that your advisee has requested a guidance consultation. Please coordinate with the student as needed.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Student</td><td class="val"><strong>{{ $studentName }}</strong></td></tr>
    <tr><td class="lbl">Concern</td><td class="val">{{ $concern }}</td></tr>
    <tr><td class="lbl">Requested Date / Time</td><td class="val">{{ $requested ?? 'Not yet specified' }}</td></tr>
    <tr><td class="lbl">Assigned Guidance Personnel</td><td class="val">{{ $assignedPersonnel ?? 'Not yet assigned' }}</td></tr>
</table>
@endsection

@section('footer-note')Regards — PSHS-CRC Guidance Office@endsection
