@extends('emails.layouts.base')

@section('header-gradient'){{ $headerColor ?? '#6366f1' }}@endsection
@section('header-title'){{ $headline }}@endsection
@section('header-subtitle','PSHS-CRC MIS — Document Tracking System')

@section('content')
<p class="greeting">Hello <strong>{{ $recipientName }}</strong>,</p>
<p class="lead">{!! $intro !!}</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Tracking No.</td><td class="val"><strong>{{ $document->tracking_no }}</strong></td></tr>
    <tr><td class="lbl">Type</td><td class="val">{{ $document->documentType->name ?? '—' }}</td></tr>
    <tr><td class="lbl">Subject</td><td class="val">{{ $document->subject }}</td></tr>
    <tr><td class="lbl">Origin</td><td class="val">
        @php $o = $document->origin_type ?? 'internal'; @endphp
        <span class="badge {{ $o === 'external' ? 'badge-green' : 'badge-blue' }}">{{ ucfirst($o) }}</span>
    </td></tr>
    <tr><td class="lbl">Priority</td><td class="val">
        @php $p = $document->priority ?? 'Normal'; @endphp
        <span class="badge {{ $p === 'Rush' ? 'badge-red' : ($p === 'Urgent' ? 'badge-amber' : 'badge-blue') }}">{{ $p }}</span>
    </td></tr>
    @foreach($extraRows ?? [] as $label => $val)
    <tr><td class="lbl">{{ $label }}</td><td class="val">{{ $val }}</td></tr>
    @endforeach
    @if($document->deadline_at)
    <tr><td class="lbl">Deadline</td><td class="val" style="color:#dc2626;font-weight:600;">{{ $document->deadline_at->format('F j, Y g:i A') }}</td></tr>
    @endif
</table>

<p style="font-size:13px;color:#64748b;margin-top:12px;">Log in to CRCMIS to view the document, scan, and full routing trail.</p>
@endsection

@section('actions')
<a class="btn btn-primary" href="{{ $viewUrl }}" style="background:{{ $headerColor ?? '#6366f1' }}">{{ $btnLabel ?? 'View Document' }} →</a>
@endsection
