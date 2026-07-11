@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#4f46e5,#6366f1)')
@section('header-title','IPCR Ready for Endorsement')
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
<p class="lead">An IPCR under your division has been <strong>rated by {{ $rater->name }}</strong> and is now ready for your endorsement to the PMT.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Employee</td><td class="val"><strong>{{ $ipcr->user?->name ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Position</td><td class="val">{{ $ipcr->user?->position ?? '—' }}</td></tr>
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    <tr><td class="lbl">Rated By</td><td class="val">{{ $rater->name }}<br><span style="font-size:12px;color:#64748b;">{{ $rater->position ?? '' }}</span></td></tr>
    @if($overallAvg)
    <tr><td class="lbl">Overall Average</td><td class="val"><strong>{{ number_format($overallAvg, 2) }}</strong></td></tr>
    @endif
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-purple">{{ $ipcr->status }}</span></td></tr>
</table>

<p style="margin-top:16px;font-size:14px;color:#475569;">Open the Performance Management module to review and submit this IPCR to the PMT.</p>
@endsection
