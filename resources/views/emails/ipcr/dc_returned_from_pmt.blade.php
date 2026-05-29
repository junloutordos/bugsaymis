@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#d97706,#f59e0b)')
@section('header-title','IPCR Returned — Revision Required')
@section('header-subtitle','Individual Performance Commitment and Review')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">Your IPCR has been <strong>returned for revision</strong> by your Division Chief following feedback from the PMT. Please review the remarks below and make the necessary corrections.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    <tr><td class="lbl">Returned By</td><td class="val">{{ $ipcr->user?->division?->divisionchief?->name ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Returned</td><td class="val">{{ now()->format('F j, Y, g:i A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-amber">{{ $ipcr->status }}</span></td></tr>
</table>

@if(!empty($ipcr->remarks))
<div class="callout callout-amber">
    <div class="callout-title">Division Chief's Remarks</div>
    {{ $ipcr->remarks }}
</div>
@endif

<div class="callout callout-blue">
    <div class="callout-title">What to do next</div>
    Update your accomplishments and self-ratings per the feedback above, then click <strong>Submit for Rating</strong> to resubmit. Your Division Chief will forward it to the PMT again after review.
</div>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ route('employee-ipcr.show', $ipcr->id) }}">Open My IPCR →</a>
@endsection
