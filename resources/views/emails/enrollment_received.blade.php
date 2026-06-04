@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#1d4ed8,#3b82f6)')
@section('header-title','Enrollment Application Received')
@section('header-subtitle','PSHS-CRC — SY {{ $application->schoolYear->name ?? "" }}')

@section('content')
<p class="greeting">Hello, {{ $application->firstname }}!</p>
<p class="lead">
    We have received your enrollment application for <strong>Grade {{ $application->grade_level }}</strong>.
    Your application is now under review by our Registrar's Office.
</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Reference No.</td><td class="val"><strong>{{ $application->reference_no }}</strong></td></tr>
    <tr><td class="lbl">Applicant</td><td class="val">{{ $application->full_name }}</td></tr>
    <tr><td class="lbl">Grade Applying For</td><td class="val">Grade {{ $application->grade_level }}</td></tr>
    <tr><td class="lbl">School Year</td><td class="val">{{ $application->schoolYear->name ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Submitted</td><td class="val">{{ $application->created_at->format('F j, Y') }}</td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">📋 What happens next?</div>
    The Registrar's Office will review your application and verify your submitted information.
    You will receive another email once a decision has been made.
    Please keep your reference number for follow-up inquiries.
</div>

<p style="font-size:13px;color:#64748b;margin-top:16px;">
    For inquiries, please contact the Registrar's Office and provide your reference number
    <strong>{{ $application->reference_no }}</strong>.
</p>
@endsection
