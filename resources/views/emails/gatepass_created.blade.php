@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0891b2,#06b6d4)')
@section('header-title','Gate Pass — Approval Needed')
@section('header-subtitle','PSHS-CRC MIS — Gate Pass System')

@section('content')
<p class="greeting">Hello <strong>{{ $gatepass->divisionChief?->name ?? 'Division Chief' }}</strong>,</p>
<p class="lead">A new gate pass has been submitted and assigned to you for approval. Please act within <strong>24 hours</strong>.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Control No.</td><td class="val"><strong>{{ $gatepass->controlno ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $gatepass->name ?? ($gatepass->employee_name ?? '—') }}</td></tr>
    <tr><td class="lbl">Badge No.</td><td class="val">{{ $gatepass->badgeNumber ?? '—' }}</td></tr>
    <tr><td class="lbl">Gate Pass Type</td><td class="val">{{ $gatepass->gatepass_type ?? '—' }}</td></tr>
    <tr><td class="lbl">Date</td><td class="val">{{ $gatepass->gatepass_date ?? '—' }}</td></tr>
    <tr><td class="lbl">Time Out / In</td><td class="val">{{ ($gatepass->gatepass_timeout ?? '—') }} / {{ ($gatepass->gatepass_timein ?? '—') }}</td></tr>
    <tr><td class="lbl">Destination</td><td class="val">{{ $gatepass->destination ?? '—' }}</td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $gatepass->purpose ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Filed</td><td class="val">{{ $gatepass->created_at?->format('F j, Y g:i A') ?? '—' }}</td></tr>
</table>
@endsection

@section('actions')
<a class="btn btn-green" href="{{ $approveUrl }}">Approve Gate Pass</a>
@if(!empty($declineUrl))<a class="btn btn-red" href="{{ $declineUrl }}">Decline</a>@endif
@endsection

@section('fallback-links')
<p>Approve: <a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>
@if(!empty($declineUrl))<p>Decline: <a href="{{ $declineUrl }}">{{ $declineUrl }}</a></p>@endif
@endsection

@section('footer-note')If you do not have permission to approve this request, you may ignore this email.@endsection
