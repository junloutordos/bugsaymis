@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#d97706,#f59e0b)')
@section('header-title','Accomplishment Returned for Revision')
@section('header-subtitle','Individual Performance Commitment and Review')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">Your submitted accomplishments have been <strong>returned for revision</strong> by your Division Chief. Please update your accomplishments, means of verification, and self-ratings, then resubmit for rating.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    <tr><td class="lbl">Returned By</td><td class="val">{{ $ipcr->user?->division?->divisionchief?->name ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Returned</td><td class="val">{{ now()->format('F j, Y, g:i A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-amber">{{ $ipcr->status }}</span></td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">What to do next</div>
    Open your IPCR, update your accomplishment descriptions and MOV links for each plan, then click <strong>Submit for Rating</strong>.
</div>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ route('employee-ipcr.show', $ipcr->id) }}">Update Accomplishments →</a>
@endsection
