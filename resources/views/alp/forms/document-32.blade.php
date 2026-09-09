@extends('alp.forms._layout')
@php
    $content = $document->content ?? [];
    $hazards = (array) data_get($content, 'hazards', []);
    $checklistItems = \App\Services\ALP\AlpComplianceService::RISK_CHECKLIST_ITEMS;
    $left = array_slice($checklistItems, 0, 8);
    $right = array_slice($checklistItems, 8);
    $checked = fn (string $item) => in_array($item, $hazards, true) ? '&#9746;' : '&#9744;';
    $mitigations = (array) data_get($content, 'mitigations', []);
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'RISK ASSESSMENT AND PREVENTIVE MEASURES PLAN',
    'periodLine' => 'S.Y. '.$cycle->schoolYear->name,
])

<h2>I. Background</h2>
<p class="field-line">ALP Name: <span class="fill">{{ $cycle->program->name }}</span></p>
<p class="field-line">ALP Adviser: <span class="fill">{{ $signatories['adviser'] ?? '' }}</span></p>
<p class="field-line">ALP President: <span class="fill">{{ $signatories['president'] ?? '' }}</span></p>
<p class="field-line">Location/Venue: <span class="fill">{{ data_get($content, 'venue_details') }}</span></p>
<p class="field-line">Time Schedule: <span class="fill"></span></p>
<p class="field-line">Number of participants (male/female): <span class="fill"></span></p>
<p><strong class="inline-label">Nature and Purpose:</strong></p>
<div class="boxed">{{ data_get($content, 'nature_and_purpose') }}</div>

<h2>II. Risk Assessment</h2>
<p><strong>RISK ASSESSMENT</strong>, identify possible hazards in relation to the nature of ALP (Check all that apply):</p>
<table class="checklist">
    <tr>
        <td style="width:50%">
            @foreach($left as $item)
                <div>{!! $checked($item) !!} {{ $item }}@if($item === 'Others'): <span class="fill">{{ data_get($content, 'other_hazard') }}</span>@endif</div>
            @endforeach
        </td>
        <td style="width:50%">
            @foreach($right as $item)
                <div>{!! $checked($item) !!} {{ $item }}</div>
            @endforeach
        </td>
    </tr>
</table>

<h2>III. Risk Level of ALP</h2>
@php($level = strtolower(data_get($content, 'risk_level', '')))
<div>
    {!! $level === 'low' ? '&#9746;' : '&#9744;' !!} Low
    &nbsp;&nbsp;&nbsp; {!! $level === 'medium' ? '&#9746;' : '&#9744;' !!} Medium
    &nbsp;&nbsp;&nbsp; {!! $level === 'high' ? '&#9746;' : '&#9744;' !!} High (Requires additional controls)
</div>

<p class="section-label">Safety and Risk Management (based on the identified risk/s, outline strategies for mitigation)</p>
<table class="bordered">
    <thead><tr><th>Identified risk/s:</th><th>Mitigation/s:</th></tr></thead>
    <tbody>
    @foreach($mitigations as $row)
        <tr><td>{{ data_get($row, 'risk') }}</td><td>{{ data_get($row, 'mitigation') }}</td></tr>
    @endforeach
    </tbody>
</table>

<div class="page-break"></div>
<h2>IV. ALP Safety Preparations</h2>
<p class="field-line">Participants with medical needs: <span class="fill">{{ data_get($content, 'participants_with_medical_needs') }}</span></p>
<p class="field-line">Emergency Contact of ALP: <span class="fill">{{ data_get($content, 'emergency_contact') }}</span></p>
<p class="field-line">Mobile Number: <span class="fill">{{ data_get($content, 'emergency_mobile') }}</span></p>
<p class="field-line">School Clinic/Nurse: <span class="fill">{{ data_get($content, 'school_clinic_nurse') }}</span></p>
<p class="field-line">Contact information: <span class="fill">{{ data_get($content, 'clinic_contact_information') }}</span></p>
<p><strong class="inline-label">Procedure in case of emergency (briefly describe the process of communication):</strong></p>
<div class="boxed">{{ data_get($content, 'emergency_procedure') }}</div>

<h2>V. Venue Safety</h2>
<p>Area/location inspected:
    {!! data_get($content, 'venue_inspected') ? '&#9746;' : '&#9744;' !!} Yes
    &nbsp;&nbsp; {!! data_get($content, 'venue_inspected') ? '&#9744;' : '&#9746;' !!} No
</p>
<p class="field-line">Details: <span class="fill">{{ data_get($content, 'venue_details') }}</span></p>

<h2>VI. Incident Report Procedure</h2>
<p class="field-line">Person responsible: <span class="fill">{{ data_get($content, 'incident_person_responsible') }}</span></p>
<p class="field-line">Where to submit report: <span class="fill">{{ data_get($content, 'incident_submit_to') }}</span></p>

@include('alp.forms._signatures', ['rows' => [
    [
        ['action' => 'Prepared by:', 'name' => $signatories['adviser'] ?? null, 'role' => 'ALP Adviser'],
        ['action' => 'Approved by:', 'name' => $signatories['coordinator'] ?? null, 'role' => 'ALP Coordinator'],
    ],
]])
@endsection
