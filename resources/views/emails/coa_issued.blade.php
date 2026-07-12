@extends('emails.layouts.base')

@section('header-title')Certificate of Appearance@endsection
@section('header-subtitle','Philippine Science High School – Caraga Region Campus')

@section('content')
@php
    $dateFrom = $visit->date_from?->format('F j, Y');
    $dateTo   = $visit->date_to && ! $visit->date_to->equalTo($visit->date_from)
        ? $visit->date_to->format('F j, Y') : null;
@endphp

<p class="greeting">Dear <strong>{{ $certificate->visitor_name }}</strong>,</p>
<p class="lead">Thank you for visiting the Philippine Science High School – Caraga Region Campus. Your official Certificate of Appearance is attached to this email as a PDF.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Control Number</td><td class="val"><strong style="font-size:15px;font-family:monospace;">{{ $certificate->control_number }}</strong></td></tr>
    <tr><td class="lbl">Name</td><td class="val">{{ $certificate->visitor_name }}</td></tr>
    @if($certificate->organization)
    <tr><td class="lbl">Organization</td><td class="val">{{ $certificate->organization }}</td></tr>
    @endif
    <tr><td class="lbl">Date of Appearance</td><td class="val">{{ $dateFrom }}@if($dateTo) &ndash; {{ $dateTo }}@endif</td></tr>
    <tr><td class="lbl">Purpose</td><td class="val">{{ $visit->purpose }}</td></tr>
</table>

<div class="callout callout-blue">
    <div class="callout-title">Verify Authenticity</div>
    This certificate carries a QR code. Anyone can confirm its authenticity by scanning the QR
    on the document or using the verification link below.
</div>
@endsection

@section('actions')
<a class="btn btn-blue" href="{{ $verifyUrl }}">Verify Certificate →</a>
@endsection
