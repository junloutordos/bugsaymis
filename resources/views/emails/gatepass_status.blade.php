@extends('emails.layouts.base')

@section('header-gradient', str_contains(strtolower($status ?? ''), 'approved') ? 'linear-gradient(90deg,#059669,#10b981)' : 'linear-gradient(90deg,#dc2626,#ef4444)')
@section('header-title','Gate Pass — {{ $status }}')
@section('header-subtitle','PSHS-CRC MIS — Gate Pass System')

@section('content')
<p class="greeting">Hello <strong>{{ $gatepass->name ?? 'Requestor' }}</strong>,</p>
<p class="lead">Your gate pass status has been updated.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Control No.</td><td class="val"><strong>{{ $gatepass->controlno ?? $gatepass->id }}</strong></td></tr>
    <tr><td class="lbl">Date</td><td class="val">{{ $gatepass->gatepass_date ?? '—' }}</td></tr>
    <tr><td class="lbl">Time Out / In</td><td class="val">{{ $gatepass->gatepass_timeout ?? '—' }} / {{ $gatepass->gatepass_timein ?? '—' }}</td></tr>
    <tr><td class="lbl">Destination</td><td class="val">{{ $gatepass->destination ?? '—' }}</td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $gatepass->purpose ?? '—' }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge {{ str_contains(strtolower($status ?? ''), 'approved') ? 'badge-green' : 'badge-red' }}">{{ $status }}</span></td></tr>
    @if(!empty($actor))<tr><td class="lbl">Actioned By</td><td class="val">{{ $actor }}</td></tr>@endif
    @if(!empty($reason))<tr><td class="lbl">Reason</td><td class="val" style="color:#991b1b;">{{ $reason }}</td></tr>@endif
</table>
@endsection

@section('actions')
@if(!empty($approveUrl) || !empty($declineUrl))
    @if(!empty($approveUrl))<a class="btn btn-green" href="{{ $approveUrl }}">Approve</a>@endif
    @if(!empty($declineUrl))<a class="btn btn-red" href="{{ $declineUrl }}">Decline</a>@endif
@endif
@endsection

@section('fallback-links')
@if(!empty($approveUrl))<p>Approve: <a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>@endif
@if(!empty($declineUrl))<p>Decline: <a href="{{ $declineUrl }}">{{ $declineUrl }}</a></p>@endif
@endsection
