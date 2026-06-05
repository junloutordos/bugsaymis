@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#0369a1,#0ea5e9)')
@section('header-title','Parent Link Request')
@section('header-subtitle','MyPisay — PSHS Caraga Region Campus')

@section('content')
<p class="greeting">Hello,</p>
<p class="lead">
    A parent or guardian is requesting to be linked to your MyPisay account so they can monitor your gate attendance. Please review this request carefully.
</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Requestor</td><td class="val"><strong>{{ $parentName }}</strong></td></tr>
    <tr><td class="lbl">Relationship</td><td class="val">{{ $relationship }}</td></tr>
    <tr><td class="lbl">Expires</td><td class="val">{{ $expiresAt }}</td></tr>
</table>

<p style="font-size:14px;color:#475569;margin:16px 0 8px;"><strong>If you approve</strong>, this person will be able to see your gate scan times and receive notifications when you enter or leave campus.</p>

<div style="text-align:center;margin:24px 0;">
    <a href="{{ $confirmUrl }}" class="btn btn-green" style="margin-right:10px;">✓ Confirm</a>
    <a href="{{ $denyUrl }}" class="btn btn-red">✕ Deny</a>
</div>

<div class="callout callout-blue">
    <div class="callout-title">🔒 Your Privacy</div>
    If you do not recognize this person or did not expect this request, click <strong>Deny</strong>. The requestor will not be notified of your decision.
</div>

<p style="font-size:13px;color:#94a3b8;margin-top:16px;">This link expires on {{ $expiresAt }}. After expiry, no action is needed.</p>
@endsection
