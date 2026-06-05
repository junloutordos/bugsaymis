@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#1a3557,#1a4480)')
@section('header-title','Email Verification')
@section('header-subtitle','MyPisay — PSHS Caraga Region Campus')

@section('content')
<p class="greeting">Hello <strong>{{ $name }}</strong>,</p>
<p class="lead">Thank you for registering with MyPisay. Use the verification code below to activate your account.</p>

<div style="text-align:center;margin:28px 0;">
    <div style="display:inline-block;background:#f0f4f8;border:2px dashed #1a3557;border-radius:12px;padding:20px 40px;">
        <p style="margin:0 0 6px;font-size:12px;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:.08em;">Verification Code</p>
        <p style="margin:0;font-size:40px;font-weight:800;color:#1a3557;letter-spacing:10px;font-family:monospace;">{{ $otp }}</p>
    </div>
</div>

<div class="callout callout-amber">
    <div class="callout-title">⏱ Code Expires in 15 Minutes</div>
    Do not share this code with anyone. PSHS-CRC will never ask for it.
</div>

<p style="font-size:13px;color:#64748b;margin-top:16px;">If you did not register for MyPisay, please ignore this email.</p>
@endsection
