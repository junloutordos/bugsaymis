@extends('emails.layouts.base')

@section('header-title')Work Request — {{ $status }}@endsection
@section('header-subtitle','Atlas — GSU Work Requests')

@section('content')
<p class="greeting">Hello <strong>{{ $request->requester?->name ?? 'Requestor' }}</strong>,</p>
@if(strtolower($status) === 'update')
<p class="lead">There is a progress update on your work request #{{ $request->id }}{{ $approver ? ' from ' . $approver : '' }}.</p>
@else
<p class="lead">Your work request #{{ $request->id }} has been <strong>{{ strtolower($status) }}</strong>{{ $approver ? ' by ' . $approver : '' }}.</p>
@endif

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Issue</td><td class="val">{{ $request->issue ?? '—' }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-slate">{{ $status }}</span></td></tr>
    @if(!empty($reason))
    <tr><td class="lbl">{{ strtolower($status) === 'update' ? 'Update' : 'Reason' }}</td><td class="val">{{ $reason }}</td></tr>
    @endif
</table>
@endsection
