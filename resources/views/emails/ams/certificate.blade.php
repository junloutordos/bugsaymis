@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#10b981,#059669)')
@section('header-title','Certificate of Participation')
@section('header-subtitle','PSHS-CRC MIS — Activity Management System')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">Congratulations! Please find attached your Certificate of Participation for the following activity:</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Activity</td><td class="val"><strong>{{ $activity->title }}</strong></td></tr>
    <tr><td class="lbl">Date</td><td class="val">
        {{ \Carbon\Carbon::parse($activity->start_date)->format('F d, Y') }}
        @if($activity->end_date && $activity->end_date != $activity->start_date)
         – {{ \Carbon\Carbon::parse($activity->end_date)->format('F d, Y') }}
        @endif
    </td></tr>
    @if($activity->venue)
    <tr><td class="lbl">Venue</td><td class="val">{{ $activity->venue }}</td></tr>
    @endif
</table>

<div class="callout callout-green">
    Your certificate is attached to this email as a PDF. You may also scan the QR code on the certificate to verify its authenticity.
</div>
@endsection
