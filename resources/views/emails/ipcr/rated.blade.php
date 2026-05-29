@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#7c3aed,#6366f1)')
@section('header-title','Your IPCR Has Been Rated')
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
    $dc = $ipcr->user?->division?->divisionchief;
@endphp

<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">Your IPCR has been <strong>rated by your Division Chief</strong> and will be forwarded to the PMT for review. Your ratings are shown below.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->rating_period }}</td></tr>
    <tr><td class="lbl">IPCR Title</td><td class="val">{{ $ipcr->title }}</td></tr>
    <tr><td class="lbl">Rated By</td><td class="val">{{ $dc?->name ?? '—' }}<br><span style="font-size:12px;color:#64748b;">{{ $dc?->position ?? '' }}</span></td></tr>
    <tr><td class="lbl">Date Rated</td><td class="val">{{ $ipcr->submitted_rating_at?->format('F j, Y, g:i A') ?? now()->format('F j, Y, g:i A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-purple">{{ $ipcr->status }}</span></td></tr>
</table>

@if($ratedPlans->count())
<p style="font-weight:600;color:#334155;margin:18px 0 6px;font-size:14px;">Supervisor Ratings by Plan</p>
<table class="rating-grid" role="presentation">
    <thead>
        <tr>
            <th style="text-align:left;width:40%">Work Plan</th>
            <th>Quality</th>
            <th>Efficiency</th>
            <th>Timeliness</th>
            <th>Average</th>
        </tr>
    </thead>
    <tbody>
        @foreach($plans as $plan)
        <tr>
            <td class="plan-col">{{ $plan->success_indicator }}</td>
            <td>{{ $plan->pivot->sup_quality ?? '—' }}</td>
            <td>{{ $plan->pivot->sup_efficiency ?? '—' }}</td>
            <td>{{ $plan->pivot->sup_timeliness ?? '—' }}</td>
            <td class="avg-val">{{ $plan->pivot->sup_average ?? '—' }}</td>
        </tr>
        @endforeach
        @if($overallAvg)
        <tr class="overall-row">
            <td class="plan-col" style="font-weight:700;color:#334155;">Overall Average</td>
            <td colspan="3"></td>
            <td class="avg-val" style="font-size:16px;">{{ number_format($overallAvg, 2) }}</td>
        </tr>
        @endif
    </tbody>
</table>

@if($adjectival)
<p style="text-align:center;font-size:15px;margin:8px 0 0;">
    Adjectival Rating: <strong style="color:#4f46e5;">{{ $adjectival }}</strong>
</p>
@endif
@endif

<div class="callout callout-blue" style="margin-top:16px;">
    <div class="callout-title">What to do next</div>
    No action needed at this time. Your IPCR is being processed for PMT review. You will receive a notification once the PMT has reviewed it.
</div>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ route('employee-ipcr.show', $ipcr->id) }}">View My IPCR →</a>
@endsection
