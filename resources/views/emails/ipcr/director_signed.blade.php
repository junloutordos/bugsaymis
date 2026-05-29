@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#1e40af,#3b82f6)')
@section('header-title','IPCR Signed by the Campus Director ✓')
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
    $ocdUser = \App\Models\User::havingRole('OCD')->first();
@endphp

<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">Your IPCR has been <strong>signed by the Campus Director</strong>. The performance review process for this rating period is now complete.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    @if($overallAvg)
    <tr><td class="lbl">Final Average</td><td class="val"><strong style="font-size:16px;">{{ number_format($overallAvg, 2) }}</strong></td></tr>
    @if($adjectival)
    <tr><td class="lbl">Adjectival Rating</td><td class="val"><strong>{{ $adjectival }}</strong></td></tr>
    @endif
    @endif
    <tr><td class="lbl">Signed By</td><td class="val">{{ $ocdUser?->name ?? 'Campus Director' }}<br><span style="font-size:12px;color:#64748b;">{{ $ocdUser?->position ?? 'Office of the Campus Director' }}</span></td></tr>
    <tr><td class="lbl">Date Signed</td><td class="val">{{ $ipcr->director_signed_at?->format('F j, Y, g:i A') ?? now()->format('F j, Y, g:i A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-green">{{ $ipcr->status }}</span></td></tr>
</table>

<div class="callout callout-green">
    <div class="callout-title">Your IPCR is complete</div>
    Log in to CRCMIS to view and download your fully signed IPCR. Keep a copy for your records.
</div>
@endsection

@section('actions')
<a class="btn btn-blue" href="{{ route('employee-ipcr.show', $ipcr->id) }}">View & Download My IPCR →</a>
@endsection
