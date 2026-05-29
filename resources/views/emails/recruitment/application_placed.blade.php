@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#059669,#10b981)')
@section('header-title','Placement Confirmation — Congratulations!')
@section('header-subtitle','PSHS-CRC MIS — Recruitment & Selection')

@section('content')
<div style="text-align:center;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:20px;margin-bottom:20px;">
    <div style="font-size:36px;">🎉</div>
    <h2 style="font-size:18px;color:#065f46;margin:8px 0 4px;">Congratulations, {{ $applicant?->first_name ?? 'Applicant' }}!</h2>
    <p style="font-size:14px;color:#047857;margin:0;">Your selection has been approved and your placement has been confirmed.</p>
</div>

<table class="details" role="presentation">
    <tr><td class="lbl">Name</td><td class="val"><strong>{{ $applicant?->full_name ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Position</td><td class="val"><strong>{{ $position }}</strong></td></tr>
    <tr><td class="lbl">Assigned Office</td><td class="val">{{ $office }}</td></tr>
    <tr><td class="lbl">Start Date</td><td class="val"><strong>{{ $start_date }}</strong></td></tr>
    @if($end_date)
    <tr><td class="lbl">End Date</td><td class="val">{{ $end_date }}</td></tr>
    @endif
    <tr><td class="lbl">Placement Ref.</td><td class="val">#{{ $placement->id }}</td></tr>
</table>

@if($remarks)
<div class="callout callout-blue">
    <div class="callout-title">Remarks from HR</div>
    {{ $remarks }}
</div>
@endif

<div class="callout callout-green">
    <div class="callout-title">Next Steps</div>
    Please report to the <strong>HR Office</strong> on or before your start date to complete your onboarding requirements. Bring the necessary documents as advised.
</div>

<p style="font-size:13px;color:#64748b;margin-top:12px;">If you have any questions, please do not hesitate to contact the HR Department.</p>
@endsection
