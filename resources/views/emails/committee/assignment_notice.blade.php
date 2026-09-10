@extends('emails.layouts.base')

@section('header-title', $headerTitle)
@section('header-subtitle', 'Committee Module')

@section('content')
<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">{{ $lead }}</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Committee</td><td class="val"><strong>{{ $committee->name }}</strong></td></tr>
    @if($committee->code)
    <tr><td class="lbl">Code</td><td class="val">{{ $committee->code }}</td></tr>
    @endif
    @if($role)
    <tr><td class="lbl">Role</td><td class="val">{{ $role }}</td></tr>
    @endif
    @if($loadUnits !== null)
    <tr><td class="lbl">Load Units</td><td class="val">{{ $loadUnits }}</td></tr>
    @endif
    @if($extraLabel)
    <tr><td class="lbl">{{ $extraLabel }}</td><td class="val">{{ $extraValue }}</td></tr>
    @endif
</table>

<p style="margin-top:16px;font-size:14px;color:#475569;">Open the Committee module for more details.</p>
@endsection
