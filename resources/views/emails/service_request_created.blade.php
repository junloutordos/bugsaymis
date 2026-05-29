@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0ea5e9,#3b82f6)')
@section('header-title','Service Request — Approval Needed')
@section('header-subtitle','PSHS-CRC MIS — GSU Service Requests')

@section('content')
<p class="greeting">Hello <strong>{{ $request->requester?->division?->division_name ?? $request->unit ?? 'Division Chief' }}</strong>,</p>
<p class="lead">A new service request has been submitted and assigned to you for approval. Please act within <strong>24 hours</strong>.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Requestor</td><td class="val">{{ $requestor ?? $request->requester?->name ?? $request->requestor ?? '—' }}</td></tr>
    <tr><td class="lbl">Service</td><td class="val">{{ $request->service_type }}
        @if($request->service_type === 'Reproduction') — {{ $request->copies ?? '—' }} copies × {{ $request->sheets_per_set ?? '—' }} sheets @endif
    </td></tr>
    <tr><td class="lbl">Date Needed</td><td class="val">{{ $request->date_needed ?? '—' }}</td></tr>
    <tr><td class="lbl">Time Needed</td><td class="val">{{ $request->time_needed ?? '—' }}</td></tr>
    <tr><td class="lbl">Purpose(s)</td><td class="val">{{ $request->purposes ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Filed</td><td class="val">{{ $request->created_at?->format('F j, Y g:i A') ?? '—' }}</td></tr>
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
