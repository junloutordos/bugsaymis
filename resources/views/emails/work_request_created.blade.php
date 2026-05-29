@extends('emails.layouts.base')

@section('header-gradient','linear-gradient(90deg,#7c3aed,#8b5cf6)')
@section('header-title','Work Request — Approval Needed')
@section('header-subtitle','PSHS-CRC MIS — GSU Work Requests')

@section('content')
<p class="greeting">Hello <strong>{{ $request->gsu_head?->name ?? 'GSU Head' }}</strong>,</p>
<p class="lead">A new work request has been submitted and requires your approval. Please act within <strong>24 hours</strong>.</p>

<table class="details" role="presentation">
    <tr><td class="lbl">Request ID</td><td class="val"><strong>#{{ $request->id }}</strong></td></tr>
    <tr><td class="lbl">Issue</td><td class="val">{{ $request->issue ?? '—' }}</td></tr>
    <tr><td class="lbl">Description</td><td class="val">{{ $request->description ?? '—' }}</td></tr>
    <tr><td class="lbl">Priority</td><td class="val"><span class="badge priority-{{ strtolower($request->priority ?? 'normal') }}">{{ ucfirst($request->priority ?? 'Normal') }}</span></td></tr>
    <tr><td class="lbl">Location</td><td class="val">{{ $request->division?->name ?? ($request->location_division_id ?? '—') }}{{ $request->office?->name ? ' / ' . $request->office->name : '' }}</td></tr>
    <tr><td class="lbl">Expected Completion</td><td class="val">{{ $request->expected_completion_date ? \Carbon\Carbon::parse($request->expected_completion_date)->toDateString() : '—' }}</td></tr>
    <tr><td class="lbl">Date Filed</td><td class="val">{{ $request->created_at?->format('F j, Y g:i A') ?? '—' }}</td></tr>
</table>

@if(!empty($approveWithAssign))
<div style="margin:16px 0 4px;">
    <p style="font-weight:600;color:#64748b;font-size:13px;margin-bottom:8px;">Quick Assign &amp; Approve</p>
    @foreach($skilledUsers as $u)
        @php $link = $approveWithAssign[$u->id] ?? null; @endphp
        @if($link)
        <a href="{{ $link }}" style="display:inline-block;margin:4px 4px 4px 0;padding:8px 12px;background:#ede9fe;border-radius:8px;color:#1e293b;text-decoration:none;font-size:13px;font-weight:500;">Assign to {{ $u->name }}</a>
        @endif
    @endforeach
</div>
@endif
@endsection

@section('actions')
@if(!empty($declineUrl))<a class="btn btn-red" href="{{ $declineUrl }}">Decline</a>@endif
@endsection

@section('footer-note')If you do not have permission to approve this request, you may ignore this email.@endsection
