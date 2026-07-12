@extends('emails.layouts.base')

@section('header-title','New Error Report Submitted')
@section('header-subtitle','Atlas MIS — Error Reporting')

@section('content')
<p class="greeting">Hello <strong>MIS Team</strong>,</p>
<p class="lead">A new error report has been submitted by a system user. Please review and take action.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Report No.</td><td class="val"><strong style="font-family:monospace;">{{ $report->report_no }}</strong></td></tr>
    <tr><td class="lbl">Submitted By</td><td class="val">{{ $report->reporter?->name ?? '—' }}@if($report->reporter?->position) <span style="color:#64748b;font-size:12px;">({{ $report->reporter->position }})</span>@endif</td></tr>
    <tr><td class="lbl">Date &amp; Time</td><td class="val">{{ $report->created_at->setTimezone('Asia/Manila')->format('F j, Y, g:i A') }} PHT</td></tr>
    <tr><td class="lbl">Title</td><td class="val"><strong>{{ $report->title }}</strong></td></tr>
    <tr><td class="lbl">Status</td><td class="val"><span class="badge badge-red">Open</span></td></tr>
    @if($report->page_url)
    <tr><td class="lbl">Page / URL</td><td class="val" style="font-size:12px;word-break:break-all;color:#475569;">{{ $report->page_url }}</td></tr>
    @endif
    @if($report->browser_info)
    <tr><td class="lbl">Browser</td><td class="val" style="font-size:12px;color:#475569;">{{ $report->browser_info['browser'] ?? '' }} on {{ $report->browser_info['os'] ?? '' }}</td></tr>
    @endif
</table>

<div class="callout callout-amber">
    <div class="callout-title">Description</div>
    {!! nl2br(e($report->description)) !!}
</div>

@if($report->screenshot_path)
<div class="callout callout-blue">
    <div class="callout-title">Screenshot Attached</div>
    A screenshot was included. View it in the MIS Error Reports dashboard.
</div>
@endif

@endsection

@section('actions')
<a href="{{ route('error-reports.index') }}" class="btn btn-primary">View All Error Reports</a>
@endsection
