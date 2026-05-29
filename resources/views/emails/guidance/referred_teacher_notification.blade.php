@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0891b2,#06b6d4)')
@section('header-title','Your Student Has Been Referred to Guidance')
@section('header-subtitle','PSHS-CRC MIS — Guidance Office')

@section('content')
<p class="greeting">Dear <strong>{{ $teacherName ?? 'Teacher' }}</strong>,</p>
<p class="lead">One of your students has been referred to the Guidance Office. Please see the scheduled consultation details below and support your student as needed.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Student Name</td><td class="val"><strong>{{ $studentName ?? '—' }}</strong></td></tr>
    <tr><td class="lbl">Consultation Date & Time</td><td class="val">{{ optional($consult->date_time_assigned)->toDayDateTimeString() ?? $consult->date_time_assigned ?? '—' }}</td></tr>
</table>
@endsection
