@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0891b2,#06b6d4)')
@section('header-title','IPCR Ready for Rating')
@section('header-subtitle','Individual Performance Commitment and Review')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">An employee has submitted their accomplishments and is ready for your rating. Open the IPCR and provide supervisor ratings (Quality, Efficiency, Timeliness) for each work plan.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Employee</td><td class="val"><strong>{{ $ipcr->user?->name ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Division</td><td class="val">{{ $ipcr->user?->division?->division_name ?? '—' }}</td></tr>
    <tr><td class="lbl">Position</td><td class="val">{{ $ipcr->user?->position ?? '—' }}</td></tr>
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    <tr><td class="lbl">Plans to Rate</td><td class="val">{{ $ipcr->plans()->count() }}</td></tr>
    <tr><td class="lbl">Submitted On</td><td class="val">{{ $ipcr->submitted_for_rating_at?->format('F j, Y, g:i A') ?? now()->format('F j, Y, g:i A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-cyan">{{ $ipcr->status }}</span></td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">Action Required</div>
    Open the IPCR in CRCMIS and for <strong>each work plan</strong> enter the supervisor ratings on a 1–5 scale:<br>
    <strong>Quality</strong> — <strong>Efficiency</strong> — <strong>Timeliness</strong>.<br>
    Once all plans are rated, click <strong>Save Ratings</strong> to finalize and submit to PMT.
</div>
@endsection

@section('actions')
<a class="btn btn-cyan" href="{{ route('division-employee-ipcr.show', $ipcr->id) }}">Rate IPCR →</a>
@endsection
