@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#7c3aed,#6366f1)')
@section('header-title','New IPCR Submitted — Review Needed')
@section('header-subtitle','Individual Performance Commitment and Review')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">An employee under your division has submitted a new IPCR target for your review and approval. Please log in to CRCMIS to review the targets and approve or return for revision.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Employee</td><td class="val"><strong>{{ $ipcr->user?->name ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Division</td><td class="val">{{ $ipcr->user?->division?->division_name ?? '—' }}</td></tr>
    <tr><td class="lbl">Position</td><td class="val">{{ $ipcr->user?->position ?? '—' }}</td></tr>
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    <tr><td class="lbl">Plans Assigned</td><td class="val">{{ $ipcr->plans()->count() }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-purple">{{ $ipcr->status }}</span></td></tr>
    <tr><td class="lbl">Date Submitted</td><td class="val">{{ $ipcr->submitted_for_review_at?->format('F j, Y, g:i A') ?? $ipcr->created_at->format('F j, Y, g:i A') }}</td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">Action Required</div>
    Open the IPCR in CRCMIS, review the employee's committed targets for each work plan, then click <strong>Approve Targets</strong> or <strong>Return for Revision</strong> with your remarks.
</div>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ route('division-employee-ipcr.show', $ipcr->id) }}">Review IPCR →</a>
@endsection
