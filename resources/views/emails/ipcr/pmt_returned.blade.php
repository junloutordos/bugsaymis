@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#dc2626,#ef4444)')
@section('header-title','IPCR Returned by PMT for Revision')
@section('header-subtitle','Individual Performance Commitment and Review')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">The PMT (Performance Management Team) has <strong>returned the IPCR for revision</strong>. Please coordinate with the employee to address the PMT's feedback, then resubmit.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Employee</td><td class="val"><strong>{{ $ipcr->user?->name ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Division</td><td class="val">{{ $ipcr->user?->division?->division_name ?? '—' }}</td></tr>
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    <tr><td class="lbl">Date Returned</td><td class="val">{{ now()->format('F j, Y, g:i A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-red">{{ $ipcr->status }}</span></td></tr>
</table>

<div class="callout callout-red">
    <div class="callout-title">Action Required</div>
    Review the PMT's feedback in CRCMIS, inform the employee of the required revisions, then use <strong>Return to Employee</strong> and resubmit after corrections.
</div>
@endsection

@section('actions')
<a class="btn btn-red" href="{{ route('division-employee-ipcr.show', $ipcr->id) }}">View IPCR →</a>
@endsection
