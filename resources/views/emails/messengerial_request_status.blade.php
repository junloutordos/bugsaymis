@extends('emails.layouts.base')

@section('header-gradient', str_contains(strtolower($status ?? ''), 'approved') ? 'linear-gradient(90deg,#059669,#10b981)' : 'linear-gradient(90deg,#dc2626,#ef4444)')
@section('header-title','Messengerial Request — {{ $status }}')
@section('header-subtitle','PSHS-CRC MIS — Records & Messengerial Services')

@section('content')
<p class="greeting">Hello <strong>{{ $request->requestor ?? 'Requestor' }}</strong>,</p>
@if(!empty($approver) && str_contains(strtolower($status ?? ''), 'approved'))
<p class="lead">Your messengerial request has been <strong>approved</strong> by {{ $approver }}.</p>
@elseif(!empty($approver))
<p class="lead">Your messengerial request has been <strong>declined</strong> by {{ $approver }}.</p>
@else
<p class="lead">Your messengerial request status has been updated.</p>
@endif

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Reference No.</td><td class="val">{{ $request->reference_no ?? '—' }}</td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $request->purpose ?? '—' }}</td></tr>
    <tr><td class="lbl">Destination</td><td class="val">{{ $request->destination ?? '—' }}</td></tr>
    <tr><td class="lbl">Delivery Method(s)</td><td class="val">@if(is_array($request->delivery_methods)) {{ implode(', ', $request->delivery_methods) }} @else {{ $request->delivery_methods ?? '—' }} @endif</td></tr>
    <tr><td class="lbl">Consignee</td><td class="val">{{ $request->consignee_name ?? '—' }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge {{ str_contains(strtolower($status ?? ''), 'approved') ? 'badge-green' : 'badge-red' }}">{{ $status }}</span></td></tr>
    @if(!empty($reason) && str_contains(strtolower($status ?? ''), 'declined'))
    <tr><td class="lbl">Reason</td><td class="val" style="color:#991b1b;">{{ $reason }}</td></tr>
    @endif
</table>
@endsection
