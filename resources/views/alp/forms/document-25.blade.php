@extends('alp.forms._layout')
@php
    $content = $document->content ?? [];
@endphp
@section('content')
@include('alp.forms._header', [
    'title' => 'CONSTITUTION AND BY LAWS – '.$cycle->program->name,
    'periodLine' => 'S.Y. '.$cycle->schoolYear->name,
])

<h2>ALP Preamble</h2>
<p>{{ data_get($content, 'preamble') }}</p>

<div class="article-heading">Article I. Declaration of the Organization</div>
<div class="article-section"><span class="section-label">Section 1: Name and Logo</span><p>{{ data_get($content, 'a1_name_and_logo') }}</p></div>
<div class="article-section"><span class="section-label">Section 2: Purpose and Rationale</span><p>{{ data_get($content, 'a1_purpose_rationale') }}</p></div>
<div class="article-section"><span class="section-label">Section 3: Mission</span><p>{{ data_get($content, 'a1_mission') }}</p></div>
<div class="article-section"><span class="section-label">Section 4: Objectives</span>
    <ul>@foreach((array) data_get($content, 'a1_objectives', []) as $item)<li>{{ $item }}</li>@endforeach</ul>
</div>

<div class="article-heading">Article II. Membership</div>
<div class="article-section"><span class="section-label">Section 1: Eligibility/membership requirements</span><p>{{ data_get($content, 'a2_eligibility') }}</p></div>
<div class="article-section"><span class="section-label">Section 2: Active members</span><p>{{ data_get($content, 'a2_active_members') }}</p></div>
<div class="article-section"><span class="section-label">Section 3: Termination of membership</span><p>{{ data_get($content, 'a2_termination') }}</p></div>
<p><strong class="inline-label">No Hazing Provision:</strong> {{ data_get($content, 'no_hazing_provision') ? 'Hazing or initiation in any form is prohibited as a requirement for membership.' : 'Not yet provided.' }}</p>

<div class="article-heading">Article III. Provision of Rights and Privileges</div>
<div class="article-section"><span class="section-label">Section 1: Equal opportunity</span><p>{{ data_get($content, 'a3_equal_opportunity') }}</p></div>
<div class="article-section"><span class="section-label">Section 2: Right to petition</span><p>{{ data_get($content, 'a3_right_to_petition') }}</p></div>
<div class="article-section"><span class="section-label">Section 3: Freedom of Expression</span><p>{{ data_get($content, 'a3_freedom_of_expression') }}</p></div>
<div class="article-section"><span class="section-label">Section 4: Right to Public Information</span><p>{{ data_get($content, 'a3_right_to_public_information') }}</p></div>

<div class="page-break"></div>
<div class="article-heading">Article IV. Executive Board/Officers</div>
<div class="article-section"><span class="section-label">Section 1: Members of the Executive Board/Officers</span><p>{{ data_get($content, 'a4_members_board') }}</p></div>
<div class="article-section"><span class="section-label">Section 2: Role of the Executive Board/Officers</span><p>{{ data_get($content, 'a4_role_board') }}</p></div>
<div class="article-section"><span class="section-label">Section 3: Procedure for the appointment of the Executive Board/Officers</span><p>{{ data_get($content, 'a4_appointment_procedure') }}</p></div>
<div class="article-section"><span class="section-label">Section 4: Duties of the Adviser</span><p>{{ data_get($content, 'a4_adviser_duties') }}</p></div>

<div class="article-heading">Article V. Meetings</div>
<div class="article-section"><span class="section-label">Section 1: Schedule</span><p>{{ data_get($content, 'a5_schedule') }}</p></div>
<div class="article-section"><span class="section-label">Section II: Meeting process</span><p>{{ data_get($content, 'a5_meeting_process') }}</p></div>
<div class="article-section"><span class="section-label">Section III: Absenteeism</span><p>{{ data_get($content, 'a5_absenteeism') }}</p></div>

<div class="article-heading">Article VI. Amendments</div>
<div class="article-section"><span class="section-label">Section 1: Procedure of amendments</span><p>{{ data_get($content, 'a6_amendments') }}</p></div>

@include('alp.forms._signatures', ['rows' => [
    [['action' => 'Prepared by:', 'name' => $signatories['president'] ?? null, 'role' => 'ALP President']],
    [
        ['action' => 'Approved by:', 'name' => $signatories['adviser'] ?? null, 'role' => 'ALP Adviser'],
        ['action' => 'Noted by:', 'name' => $signatories['coordinator'] ?? null, 'role' => 'ALP Coordinator'],
    ],
    [['action' => 'Recommended by:', 'name' => null, 'role' => 'Assistant CID Chief for Student Affairs/DSA Chief']],
]])
@endsection
