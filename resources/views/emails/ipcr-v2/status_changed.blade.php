@extends('emails.layouts.base')

@section('header-title', 'IPCR V2 Status Update')
@section('header-subtitle', 'Individual Performance Commitment and Review — V2')

@section('content')
<p class="greeting">Dear <strong>{{ $recipient->name }}</strong>,</p>
<p class="lead">The IPCR V2 for <strong>{{ $ipcr->user->name }}</strong> is now <strong>{{ $newStatus }}</strong>.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Rating Period</td><td class="val">{{ $ipcr->period?->label ?? '—' }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-blue">{{ $newStatus }}</span></td></tr>
    <tr><td class="lbl">Date</td><td class="val">{{ now()->format('F j, Y, g:i A') }}</td></tr>
</table>

@if(!empty($remarks))
<div class="callout callout-amber">
    <div class="callout-title">Remarks</div>
    {{ $remarks }}
</div>
@endif
@endsection
