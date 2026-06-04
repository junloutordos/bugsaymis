@extends('emails.layouts.base')

@php
    $approved = $application->status === 'approved';
@endphp

@section('header-gradient', $approved
    ? 'linear-gradient(90deg,#15803d,#22c55e)'
    : 'linear-gradient(90deg,#b91c1c,#ef4444)')
@section('header-title', $approved ? 'Enrollment Approved' : 'Enrollment Not Approved')
@section('header-subtitle','PSHS-CRC — SY {{ $application->schoolYear->name ?? "" }}')

@section('content')
<p class="greeting">Hello, {{ $application->firstname }}!</p>

@if($approved)
<p class="lead">
    Congratulations! Your enrollment application for <strong>Grade {{ $application->grade_level }}</strong>
    has been <strong style="color:#15803d;">approved</strong>. You are now officially enrolled.
</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Reference No.</td><td class="val"><strong>{{ $application->reference_no }}</strong></td></tr>
    <tr><td class="lbl">Student Name</td><td class="val">{{ $application->full_name }}</td></tr>
    <tr><td class="lbl">PSHS ID</td><td class="val"><strong>{{ $application->pisays_id_assigned }}</strong></td></tr>
    <tr><td class="lbl">Grade</td><td class="val">Grade {{ $application->grade_level }}</td></tr>
    <tr><td class="lbl">School Year</td><td class="val">{{ $application->schoolYear->name ?? '—' }}</td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">📌 Next Steps</div>
    Please present this email or your reference number when reporting to school.
    Your section assignment will be communicated separately.
</div>

@else
<p class="lead">
    We regret to inform you that your enrollment application for <strong>Grade {{ $application->grade_level }}</strong>
    has <strong style="color:#b91c1c;">not been approved</strong> at this time.
</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Reference No.</td><td class="val"><strong>{{ $application->reference_no }}</strong></td></tr>
    <tr><td class="lbl">Applicant</td><td class="val">{{ $application->full_name }}</td></tr>
</table>

@if($application->remarks)
<div class="callout callout-blue">
    <div class="callout-title">📝 Reason</div>
    {{ $application->remarks }}
</div>
@endif

<p style="font-size:13px;color:#64748b;margin-top:16px;">
    If you believe this is in error or wish to inquire further, please contact the
    Registrar's Office with your reference number <strong>{{ $application->reference_no }}</strong>.
</p>
@endif
@endsection
