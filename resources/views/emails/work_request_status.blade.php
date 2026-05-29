@extends('emails.layouts.base')

@section('header-gradient', str_contains(strtolower($status ?? ''), 'approved') ? 'linear-gradient(90deg,#059669,#10b981)' : (str_contains(strtolower($status ?? ''), 'declined') ? 'linear-gradient(90deg,#dc2626,#ef4444)' : 'linear-gradient(90deg,#7c3aed,#8b5cf6)'))
@section('header-title','Work Request — {{ $status }}')
@section('header-subtitle','PSHS-CRC MIS — GSU Work Requests')

@section('content')
<p class="greeting">Hello <strong>{{ $request->requester?->name ?? 'Requestor' }}</strong>,</p>
<p class="lead">Your work request #{{ $request->id }} has been <strong>{{ strtolower($status) }}</strong>{{ $approver ? ' by ' . $approver : '' }}.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Issue</td><td class="val">{{ $request->issue ?? '—' }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-slate">{{ $status }}</span></td></tr>
    @if(!empty($reason))
    <tr><td class="lbl">Reason</td><td class="val">{{ $reason }}</td></tr>
    @endif
</table>
@endsection
