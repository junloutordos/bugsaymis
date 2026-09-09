@extends('alp.forms._layout')
@php
    $content = $document->content ?? [];
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'APPLICATION LETTER FOR RECOGNITION',
    'periodLine' => 'S.Y. '.$cycle->schoolYear->name,
])

<p class="field-line">Date: <span class="fill">{{ data_get($content, 'date') ?: now()->format('F j, Y') }}</span></p>
<p><strong>{{ $signatories['coordinator'] ?? '_________________________' }}</strong><br>ALP Coordinator</p>
<p>Greetings of integrity, excellence, and service!</p>

<p class="section-label">Introductory paragraph</p>
<p>{{ data_get($content, 'background') }}</p>

<p class="section-label">Intention and purpose of the application</p>
<p>{{ data_get($content, 'purpose') }}</p>

<p class="section-label">Membership information</p>
<p>{{ data_get($content, 'membership_information') }}</p>

<p class="section-label">Commitment Statement</p>
<p>{{ data_get($content, 'commitment') }}</p>

<p class="section-label">Closing statement</p>
<p>{{ data_get($content, 'closing') }}</p>

<p style="margin-top:10px">Respectfully,</p>

@include('alp.forms._signatures', ['rows' => [
    [['action' => '', 'name' => $signatories['president'] ?? null, 'role' => 'ALP President']],
    [
        ['action' => 'Prepared by:', 'name' => $signatories['adviser'] ?? null, 'role' => 'ALP Adviser'],
        ['action' => 'Noted by:', 'name' => $signatories['coordinator'] ?? null, 'role' => 'ALP Coordinator'],
    ],
    [
        ['action' => 'Recommended by:', 'name' => null, 'role' => 'Assistant CID Chief for Student Affairs/DSA Chief'],
        ['action' => 'Approved by:', 'name' => null, 'role' => 'Campus Director'],
    ],
]])
@endsection
