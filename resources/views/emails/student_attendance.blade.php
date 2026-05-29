@extends('emails.layouts.base')

@section('header-gradient'){{ $scanType === 'in' ? 'linear-gradient(90deg,#059669,#10b981)' : 'linear-gradient(90deg,#d97706,#f59e0b)' }}@endsection
@section('header-title','Student Attendance Alert')
@section('header-subtitle','Philippine Science High School – Caraga Region Campus')

@section('content')
<p class="greeting">Hello <strong>{{ $parentName }}</strong>,</p>
<p class="lead">This is an automated notification to inform you that your child has been logged at the school gate.</p>

<div style="text-align:center;margin:16px 0;">
    <span class="badge {{ $scanType === 'in' ? 'badge-green' : 'badge-amber' }}" style="font-size:14px;padding:6px 16px;">
        {{ $scanTypeLabel }}
    </span>
</div>

<p style="text-align:center;font-size:22px;font-weight:700;color:#0f172a;margin:8px 0 16px;">{{ $studentName }}</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Status</td><td class="val">{{ $scanType === 'in' ? 'Arrived at school' : 'Left school' }}</td></tr>
    <tr><td class="lbl">Date & Time</td><td class="val"><strong>{{ $scanTime }}</strong></td></tr>
    @if($gateLocation)
    <tr><td class="lbl">Gate</td><td class="val">{{ $gateLocation }}</td></tr>
    @endif
</table>

<p style="font-size:13px;color:#64748b;margin-top:12px;">If you believe this notification was sent in error, please contact the school office.</p>
@endsection
