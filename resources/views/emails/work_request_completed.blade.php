@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#059669,#10b981)')
@section('header-title','Work Request Completed ✓')
@section('header-subtitle','PSHS-CRC MIS — GSU Work Requests')

@section('content')
<p class="greeting">Hello <strong>{{ $request->requester?->name ?? 'Requester' }}</strong>,</p>
<p class="lead">Your work request (ID: <strong>#{{ $request->id }}</strong>) has been <strong>completed</strong>.</p>

@php
    $completedDate = null;
    try { if(!empty($request->date_completed)) $completedDate = \Carbon\Carbon::parse($request->date_completed)->format('F d, Y'); } catch(\Throwable $e) {}
@endphp

<table class="details" role="presentation">
    <tr><td class="lbl">Issue</td><td class="val">{{ $request->issue ?? '—' }}</td></tr>
    <tr><td class="lbl">Action Taken</td><td class="val">{{ $request->action_taken ?? '—' }}</td></tr>
    <tr><td class="lbl">Completed By</td><td class="val">{{ $request->actedBy?->name ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Completed</td><td class="val">{{ $completedDate ?? '—' }}</td></tr>
</table>

<div class="callout callout-green">
    <div class="callout-title">Issue resolved</div>
    If the issue has not been fully resolved, please file a follow-up work request in CRCMIS.
</div>
@endsection
