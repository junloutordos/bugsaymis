@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0891b2,#06b6d4)')
@section('header-title','IPCR Submitted to HR')
@section('header-subtitle','Individual Performance Commitment and Review')

@section('content')
@php
    $plans = $ipcr->plans;
    $ratedPlans = $plans->filter(fn($p) => !is_null($p->pivot->sup_average));
    $overallAvg = $ratedPlans->count()
        ? round($ratedPlans->sum(fn($p) => (float) $p->pivot->sup_average) / $ratedPlans->count(), 2)
        : null;
@endphp

<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">An IPCR has been submitted to HR by the Division Chief for processing and submission to PMT.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Employee</td><td class="val"><strong>{{ $ipcr->user?->name ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Division</td><td class="val">{{ $ipcr->user?->division?->division_name ?? '—' }}</td></tr>
    <tr><td class="lbl">Position</td><td class="val">{{ $ipcr->user?->position ?? '—' }}</td></tr>
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    <tr><td class="lbl">Plans</td><td class="val">{{ $plans->count() }}</td></tr>
    @if($overallAvg)
    <tr><td class="lbl">Overall Average</td><td class="val"><strong>{{ number_format($overallAvg, 2) }}</strong></td></tr>
    @endif
    <tr><td class="lbl">Submitted On</td><td class="val">{{ $ipcr->submitted_to_hr_at?->format('F j, Y, g:i A') ?? now()->format('F j, Y, g:i A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-cyan">{{ $ipcr->status }}</span></td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">For HR Action</div>
    Review this IPCR in CRCMIS and submit it to the PMT for review when ready.
</div>
@endsection

@section('actions')
<a class="btn btn-cyan" href="{{ route('hr-ipcr.show', $ipcr->id) }}">Process IPCR →</a>
@endsection
