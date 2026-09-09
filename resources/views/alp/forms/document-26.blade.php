@extends('alp.forms._layout')
@php
    $content = $document->content ?? [];
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'ACCEPTANCE LETTER OF ADVISERSHIP',
    'periodLine' => 'S.Y. '.$cycle->schoolYear->name,
])

<p class="field-line">Date: <span class="fill">{{ data_get($content, 'date') ?: now()->format('F j, Y') }}</span></p>
<p><strong>{{ $signatories['adviser'] ?? '_________________________' }}</strong><br>Designation</p>
<p>Dear Sir/Ma'am,</p>
<p>Greetings of integrity, excellence, and service!</p>

<p class="section-label">Introduction</p>
<p>{{ data_get($content, 'introduction') }}</p>

<p class="section-label">Purpose and objective of writing the letter to the prospected adviser</p>
<p>{{ data_get($content, 'purpose') }}</p>

<p class="section-label">Closing statement</p>
<p>{{ data_get($content, 'closing') }}</p>

<p style="margin-top:10px">Respectfully,</p>

@include('alp.forms._signatures', ['rows' => [
    [['action' => '', 'name' => $signatories['president'] ?? null, 'role' => 'ALP President']],
    [
        ['action' => 'Accepted by:', 'name' => $signatories['adviser'] ?? null, 'role' => 'ALP Adviser'],
        ['action' => 'Noted by:', 'name' => $signatories['coordinator'] ?? null, 'role' => 'ALP Coordinator'],
    ],
    [
        ['action' => 'Recommended by:', 'name' => null, 'role' => 'Assistant CID Chief for Student Affairs/DSA Chief'],
        ['action' => 'Approved by:', 'name' => null, 'role' => 'Campus Director'],
    ],
]])
@endsection
