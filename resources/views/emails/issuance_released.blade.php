@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(135deg,#060e50 0%,#1447c0 65%,#0093b8 100%)')
@section('header-title','Official Issuance — {{ $issuance->type_label }}')
@section('header-subtitle','Philippine Science High School – Caraga Region Campus')

@section('content')
@php
    $sig = $issuance->signature ?? null;
    $signer = $sig?->signer ?? $issuance->creator;
    $recipientLabel = match($issuance->recipient_type) {
        'all'        => 'All Active Personnel',
        'office'     => 'Selected Offices',
        'individual' => 'Named Recipients',
        default      => ucfirst($issuance->recipient_type),
    };
    $preview = null;
    if ($issuance->content && !$issuance->attachment_path) {
        $preview = strip_tags($issuance->content);
        $preview = mb_strlen($preview) > 300 ? mb_substr($preview, 0, 300) . '…' : $preview;
    }
@endphp

<p class="greeting">Dear <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">A new official issuance has been released and addressed to you. Please review the document and acknowledge receipt at your earliest convenience.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Control Number</td><td class="val"><strong style="font-size:15px;font-family:monospace;">{{ $issuance->control_number }}</strong></td></tr>
    <tr><td class="lbl">Type</td><td class="val"><span class="badge badge-blue">{{ $issuance->type_label }}</span></td></tr>
    <tr><td class="lbl">Title / Subject</td><td class="val">{{ $issuance->title }}</td></tr>
    <tr><td class="lbl">Addressed To</td><td class="val">{{ $recipientLabel }}</td></tr>
    <tr><td class="lbl">Signed By</td><td class="val">
        <strong>{{ $signer?->name ?? 'OCD' }}</strong>
        @if($signer?->position)
        <br><span style="font-size:12px;color:#64748b;">{{ $signer->position }}</span>
        @endif
    </td></tr>
    <tr><td class="lbl">Date Released</td><td class="val">{{ $issuance->released_at?->format('F j, Y g:i A') }}</td></tr>
</table>

@if($preview)
<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px 16px;margin:16px 0;font-size:13px;color:#475569;line-height:1.6;">
    <div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#94a3b8;margin-bottom:6px;">Content Preview</div>
    {{ $preview }}
</div>
@endif

<div class="callout callout-blue">
    <div class="callout-title">Action Required — Acknowledge Receipt</div>
    Log in to CRCMIS, open this issuance, and click <strong>Acknowledge Receipt</strong> to confirm you have read and received this official document. This is required for all recipients.
</div>
@endsection

@section('actions')
<a class="btn btn-blue" href="{{ $viewUrl }}">View &amp; Acknowledge →</a>
<a class="btn btn-cyan" href="{{ $verifyUrl }}">Verify Authenticity</a>
@endsection
