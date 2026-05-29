@extends('emails.layouts.base')

@php
    $gradients = [
        'placement' => 'linear-gradient(90deg,#059669,#10b981)',
        'ranking'   => 'linear-gradient(90deg,#059669,#10b981)',
        'selection' => 'linear-gradient(90deg,#059669,#10b981)',
        'exam'      => 'linear-gradient(90deg,#d97706,#f59e0b)',
        'interview' => 'linear-gradient(90deg,#d97706,#f59e0b)',
        'rejected'  => 'linear-gradient(90deg,#dc2626,#ef4444)',
        'withdrawn' => 'linear-gradient(90deg,#64748b,#94a3b8)',
    ];
    $badgeMap = [
        'placement' => 'badge-green',  'ranking'   => 'badge-green',
        'selection' => 'badge-cyan',   'exam'      => 'badge-amber',
        'interview' => 'badge-purple', 'rejected'  => 'badge-red',
        'withdrawn' => 'badge-slate',
    ];
@endphp

@section('header-gradient'){{ $gradients[$stage] ?? 'linear-gradient(90deg,#3b82f6,#2563eb)' }}@endsection
@section('header-title'){{ $subject }}@endsection
@section('header-subtitle','PSHS-CRC MIS — Recruitment & Selection')

@section('content')
<p class="greeting">Dear <strong>{{ $applicant?->full_name ?? 'Applicant' }}</strong>,</p>
<p class="lead">We would like to inform you that the status of your application has been updated.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Position</td><td class="val"><strong>{{ $position }}</strong></td></tr>
    <tr><td class="lbl">Application No.</td><td class="val">#{{ $application->id }}</td></tr>
    <tr><td class="lbl">Current Stage</td><td class="val"><span class="badge {{ $badgeMap[$stage] ?? 'badge-blue' }}" style="text-transform:capitalize;">{{ str_replace('_', ' ', $stage) }}</span></td></tr>
    <tr><td class="lbl">Date Updated</td><td class="val">{{ now()->format('F j, Y') }}</td></tr>
</table>

@if($remarks)
<div class="callout callout-blue">
    <div class="callout-title">Remarks</div>
    {{ $remarks }}
</div>
@endif

@if($stage === 'rejected')
<div class="callout callout-blue">
    We appreciate the time you invested in our selection process and encourage you to apply for future opportunities.
</div>
@elseif($stage === 'placement')
<div class="callout callout-green">
    <div class="callout-title">Congratulations!</div>
    Please report to the HR Office to complete your onboarding requirements.
</div>
@else
<p style="font-size:13px;color:#64748b;margin-top:12px;">You will be notified of further updates. If you have questions, please contact the HR Office.</p>
@endif
@endsection
