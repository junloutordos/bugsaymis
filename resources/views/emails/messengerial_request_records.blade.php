@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#f97316,#fb923c)')
@section('header-title','Messengerial Request — Records Action Required')
@section('header-subtitle','PSHS-CRC MIS — Records & Messengerial Services')

@section('content')
<p class="greeting">Hello Records Team,</p>
<p class="lead">A messengerial request has been approved by the Division Chief and requires your action in CRCMIS.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $request->requestor ?? '—' }}</td></tr>
    <tr><td class="lbl">Unit / Office</td><td class="val">{{ $request->unit ?? '—' }}</td></tr>
    <tr><td class="lbl">Reference No.</td><td class="val">{{ $request->reference_no ?? '—' }}</td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $request->purpose ?? '—' }}</td></tr>
    <tr><td class="lbl">Destination</td><td class="val">{{ $request->destination ?? '—' }}</td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">Action Required</div>
    Log in to CRCMIS and process this approved messengerial request.
</div>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ $processUrl ?? url('/messengerial') }}">Process Request →</a>
@endsection
