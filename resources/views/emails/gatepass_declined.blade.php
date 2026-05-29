@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#dc2626,#ef4444)')
@section('header-title','Gate Pass Declined')
@section('header-subtitle','PSHS-CRC MIS — Gate Pass System')

@section('content')
<p class="greeting">Hello <strong>{{ $gatepass->name ?? ($gatepass->employee_name ?? 'Requester') }}</strong>,</p>
<p class="lead">Your gate pass request has been <strong>declined</strong>.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Control No.</td><td class="val"><strong>{{ $gatepass->controlno ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Date</td><td class="val">{{ $gatepass->gatepass_date ?? '—' }}</td></tr>
    <tr><td class="lbl">Badge</td><td class="val">{{ $gatepass->badgeNumber ?? '—' }}</td></tr>
    <tr><td class="lbl">Destination</td><td class="val">{{ $gatepass->destination ?? '—' }}</td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $gatepass->purpose ?? '—' }}</td></tr>
</table>

@if(!empty($reason))
<div class="callout callout-red">
    <div class="callout-title">Reason for Decline</div>
    {{ $reason }}
</div>
@endif

<p style="font-size:13px;color:#64748b;margin-top:12px;">If you have questions about this decision, please contact your Division Chief.</p>
@endsection
