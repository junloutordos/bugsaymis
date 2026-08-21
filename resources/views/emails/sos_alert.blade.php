@extends('emails.layouts.base')

@section('header-title')SOS Alert — {{ ucfirst(str_replace('_',' ', $alert->alert_type)) }}@endsection
@section('header-subtitle','Atlas — Emergency Response')

@section('content')
<p class="greeting">Hello{{ $recipient ? ' ' . $recipient->name : '' }},</p>
<p class="lead">An SOS emergency alert has been triggered on campus. Please respond immediately.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Alert ID</td><td class="val"><strong>#{{ $alert->id }}</strong></td></tr>
    <tr><td class="lbl">Type</td><td class="val">{{ ucfirst(str_replace('_',' ', $alert->alert_type)) }}</td></tr>
    <tr><td class="lbl">Triggered At</td><td class="val">{{ $alert->triggered_at->timezone('Asia/Manila')->format('F j, Y — g:i:s A') }}</td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-red">{{ ucfirst($alert->status) }}</span></td></tr>
</table>

<p class="lead">Log in to the Atlas SOS Command Center to acknowledge and respond.</p>
@endsection
