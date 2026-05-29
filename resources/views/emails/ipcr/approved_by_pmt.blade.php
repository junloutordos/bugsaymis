@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#059669,#10b981)')
@section('header-title','IPCR Approved by PMT ✓')
@section('header-subtitle','Individual Performance Commitment and Review')

@section('content')
@php
    $plans = $ipcr->plans;
    $ratedPlans = $plans->filter(fn($p) => !is_null($p->pivot->sup_average));
    $overallAvg = $ratedPlans->count()
        ? round($ratedPlans->sum(fn($p) => (float) $p->pivot->sup_average) / $ratedPlans->count(), 2)
        : null;
    $adjectival = null;
    if ($overallAvg !== null) {
        $adjectival = $overallAvg >= 4.5 ? 'Outstanding'
            : ($overallAvg >= 3.5 ? 'Very Satisfactory'
            : ($overallAvg >= 2.5 ? 'Satisfactory'
            : ($overallAvg >= 1.5 ? 'Unsatisfactory' : 'Poor')));
    }
@endphp

<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">Your IPCR has been <strong>approved by the Performance Management Team (PMT)</strong>. It is now awaiting the Campus Director's signature to complete the process.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    @if($overallAvg)
    <tr><td class="lbl">Overall Average</td><td class="val"><strong>{{ number_format($overallAvg, 2) }}</strong></td></tr>
    @if($adjectival)
    <tr><td class="lbl">Adjectival Rating</td><td class="val"><strong>{{ $adjectival }}</strong></td></tr>
    @endif
    @endif
    <tr><td class="lbl">Approved On</td><td class="val">{{ now()->format('F j, Y, g:i A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-green">{{ $ipcr->status }}</span></td></tr>
</table>

<div class="callout callout-green">
    <div class="callout-title">What to do next</div>
    No action needed. Await the Campus Director's electronic signature, after which your IPCR will be fully completed. You will receive another notification once signed.
</div>
@endsection

@section('actions')
<a class="btn btn-green" href="{{ route('employee-ipcr.show', $ipcr->id) }}">View My IPCR →</a>
@endsection
