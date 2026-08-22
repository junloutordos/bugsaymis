@extends('emails.layouts.base')

@section('header-title')Emergency Alert — {{ $alert->title }}@endsection
@section('header-subtitle','Atlas — Campus-Wide Broadcast')

@section('content')
<p class="greeting">Hello{{ $recipient ? ' ' . $recipient->name : '' }},</p>
<p class="lead">A campus-wide emergency alert has been broadcast.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Alert ID</td><td class="val"><strong>#{{ $alert->id }}</strong></td></tr>
    <tr><td class="lbl">Severity</td><td class="val"><span class="badge badge-red">{{ ucfirst($alert->severity) }}</span></td></tr>
    <tr><td class="lbl">Message</td><td class="val">{{ $alert->message }}</td></tr>
    <tr><td class="lbl">Broadcast At</td><td class="val">{{ $alert->created_at->timezone('Asia/Manila')->format('F j, Y — g:i:s A') }}</td></tr>
</table>

<p class="lead">Log in to Atlas for further details.</p>
@endsection
