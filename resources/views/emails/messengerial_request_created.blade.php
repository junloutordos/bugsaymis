@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#6366f1,#8b5cf6)')
@section('header-title','Messengerial Request — Approval Needed')
@section('header-subtitle','PSHS-CRC MIS — Records & Messengerial Services')

@section('content')
<p class="greeting">Hello <strong>{{ $request->unit ?? 'Division Chief' }}</strong>,</p>
<p class="lead">A new messengerial service request has been submitted and requires your approval. Please act within <strong>24 hours</strong>.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $request->requestor ?? '—' }}</td></tr>
    <tr><td class="lbl">Unit / Office</td><td class="val">{{ $request->unit ?? '—' }}</td></tr>
    <tr><td class="lbl">Reference No.</td><td class="val">{{ $request->reference_no ?? '—' }}</td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $request->purpose ?? '—' }}</td></tr>
    <tr><td class="lbl">Destination</td><td class="val">{{ $request->destination ?? '—' }}</td></tr>
    <tr><td class="lbl">Delivery Method(s)</td><td class="val">@if(is_array($request->delivery_methods)) {{ implode(', ', $request->delivery_methods) }} @else {{ $request->delivery_methods ?? '—' }} @endif</td></tr>
    <tr><td class="lbl">Package Type</td><td class="val">@if(is_array($request->messengerial_kinds)) {{ implode(', ', $request->messengerial_kinds) }} @else {{ $request->messengerial_kinds ?? '—' }} @endif</td></tr>
    <tr><td class="lbl">Consignee</td><td class="val">{{ $request->consignee_name ?? '—' }}{{ $request->consignee_contact ? ' (' . $request->consignee_contact . ')' : '' }}</td></tr>
    <tr><td class="lbl">Consignee Email</td><td class="val">{{ $request->consignee_email ?? '—' }}</td></tr>
</table>
@endsection

@section('actions')
@if(!empty($approveUrl))<a class="btn btn-green" href="{{ $approveUrl }}">Approve</a>@endif
@if(!empty($declineUrl))<a class="btn btn-red" href="{{ $declineUrl }}">Decline</a>@endif
@endsection

@section('fallback-links')
@if(!empty($approveUrl))<p>Approve: <a href="{{ $approveUrl }}">{{ $approveUrl }}</a></p>@endif
@if(!empty($declineUrl))<p>Decline: <a href="{{ $declineUrl }}">{{ $declineUrl }}</a></p>@endif
@endsection

@section('footer-note')If you do not have permission to approve this request, you may ignore this email.@endsection
