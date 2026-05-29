@extends('emails.layouts.base')

@section('header-gradient', str_contains(strtolower($status ?? ''), 'approved') ? 'linear-gradient(90deg,#059669,#10b981)' : 'linear-gradient(90deg,#dc2626,#ef4444)')
@section('header-title','Service Request — {{ $status }}')
@section('header-subtitle','PSHS-CRC MIS — GSU Service Requests')

@section('content')
<p class="greeting">Hello <strong>{{ $requestor ?? ($request->requestor ?? 'Requestor') }}</strong>,</p>
@if(str_contains(strtolower($status ?? ''), 'approved'))
<p class="lead">Your service request has been <strong>approved</strong>. It will be processed according to your specified date and time.</p>
@else
<p class="lead">Your service request has been <strong>declined</strong>.</p>
@endif

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Service</td><td class="val">{{ $request->service_type }}
        @if($request->service_type === 'Reproduction') — {{ $request->copies ?? '—' }} copies × {{ $request->sheets_per_set ?? '—' }} sheets @endif
    </td></tr>
    <tr><td class="lbl">Date Needed</td><td class="val">{{ $request->date_needed ?? '—' }}</td></tr>
    <tr><td class="lbl">Time Needed</td><td class="val">{{ $request->time_needed ?? '—' }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge {{ str_contains(strtolower($status ?? ''), 'approved') ? 'badge-green' : 'badge-red' }}">{{ $status }}</span></td></tr>
    @if(!empty($reason))
    <tr><td class="lbl">Remarks</td><td class="val" @if(!str_contains(strtolower($status ?? ''), 'approved')) style="color:#991b1b;" @endif>{{ $reason }}</td></tr>
    @endif
</table>

@if(!str_contains(strtolower($status ?? ''), 'approved'))
<div class="callout callout-blue">
    <div class="callout-title">What to do next</div>
    If you need this service, you may re-file a new service request in CRCMIS with updated details.
</div>
@endif
@endsection
