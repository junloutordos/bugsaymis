@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#059669,#10b981)')
@section('header-title','IPCR Targets Approved ✓')
@section('header-subtitle','Individual Performance Commitment and Review')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">Your IPCR targets have been <strong>approved</strong>. You may now log in to CRCMIS and record your actual accomplishments for each assigned work plan.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    <tr><td class="lbl">Plans Assigned</td><td class="val">{{ $ipcr->plans()->count() }}</td></tr>
    <tr><td class="lbl">Approved By</td><td class="val">{{ $ipcr->user?->division?->divisionchief?->name ?? '—' }}</td></tr>
    <tr><td class="lbl">Date Approved</td><td class="val">{{ $ipcr->target_approved_at?->format('F j, Y, g:i A') ?? now()->format('F j, Y, g:i A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-green">{{ $ipcr->status }}</span></td></tr>
</table>

<div class="callout callout-green">
    <div class="callout-title">What to do next</div>
    Open your IPCR, go to each work plan, and fill in your actual accomplishment, means of verification (MOV link), and self-ratings (Quality, Efficiency, Timeliness). Once done, submit for rating.
</div>
@endsection

@section('actions')
<a class="btn btn-green" href="{{ route('employee-ipcr.show', $ipcr->id) }}">Open My IPCR →</a>
@endsection
