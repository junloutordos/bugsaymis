@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#6366f1,#3b82f6)')
@section('header-title', $actionType === 'new' ? 'New Document Routed to You' : 'Document Forwarded to You')
@section('header-subtitle','PSHS-CRC MIS — Document Tracking System')

@section('content')
<p class="greeting">Hello <strong>{{ $routing->receiver->name }}</strong>,</p>
<p class="lead">
    @if($actionType === 'new')
        A new document has been routed to you by <strong>{{ $routing->sender->name }}</strong> for your action.
    @else
        A document has been forwarded to you by <strong>{{ $routing->sender->name }}</strong>.
    @endif
</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Tracking No.</td><td class="val"><strong>{{ $routing->document->tracking_no }}</strong></td></tr>
    <tr><td class="lbl">Document Type</td><td class="val">{{ $routing->document->documentType->name ?? '—' }}{{ $routing->document->documentType->code ? ' ('.$routing->document->documentType->code.')' : '' }}</td></tr>
    <tr><td class="lbl">Subject</td><td class="val">{{ $routing->document->subject }}</td></tr>
    <tr><td class="lbl">Urgency</td><td class="val">
        @php $u = $routing->document->urgency; @endphp
        <span class="badge {{ $u === 'Very Urgent' ? 'badge-red' : ($u === 'Urgent' ? 'badge-amber' : 'badge-blue') }}">{{ $u }}</span>
    </td></tr>
    <tr><td class="lbl">Confidential</td><td class="val">{{ $routing->document->is_confidential ? 'Yes — Handle with care' : 'No' }}</td></tr>
    <tr><td class="lbl">Routing Step</td><td class="val">Step #{{ $routing->sequence }}</td></tr>
    @if($routing->remarks)
    <tr><td class="lbl">Sender's Remarks</td><td class="val">{{ $routing->remarks }}</td></tr>
    @endif
    <tr><td class="lbl">Date Routed</td><td class="val">{{ $routing->created_at->format('F j, Y g:i A') }}</td></tr>
</table>

@if($routing->due_at)
<div class="callout callout-amber">
    <div class="callout-title">⏰ Action Required By</div>
    <strong>{{ $routing->due_at->format('F j, Y g:i A') }}</strong>
    @if($routing->document->documentType->lead_time_hours ?? null)
    ({{ $routing->document->documentType->lead_time_hours }}-hour lead time per ARTA guidelines)
    @endif
</div>
@endif

<p style="font-size:13px;color:#64748b;margin-top:12px;">Log in to CRCMIS to view the full document, record your action, and forward to the next recipient as needed.</p>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ $viewUrl }}">View Document in System →</a>
@endsection
