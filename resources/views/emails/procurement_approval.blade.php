@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#d97706,#f59e0b)')
@section('header-title','Purchase Request — Approval Needed')
@section('header-subtitle','PSHS-CRC MIS — Procurement')

@section('content')
<p class="greeting">Hello,</p>
<p class="lead">A new purchase request has been submitted and requires your review and approval.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">PR No.</td><td class="val"><strong>{{ $procurement->pr_no ?? '#'.$procurement->id }}</strong></td></tr>
    <tr><td class="lbl">Requested By</td><td class="val">{{ $procurement->requester?->name ?? '—' }}</td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $procurement->purpose ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Filed</td><td class="val">{{ $procurement->created_at?->format('F j, Y g:i A') ?? '—' }}</td></tr>
</table>
@endsection

@section('actions')
<a class="btn btn-green" href="{{ $approveUrl }}">Approve</a>
@if(!empty($declineUrl))<a class="btn btn-red" href="{{ $declineUrl }}">Decline</a>@endif
@endsection

@section('fallback-links')
<p>Approve: <a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>
@if(!empty($declineUrl))<p>Decline: <a href="{{ $declineUrl }}">{{ $declineUrl }}</a></p>@endif
@endsection

@section('footer-note')If you do not have permission to approve this request, you may ignore this email.@endsection
