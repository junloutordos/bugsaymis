@php
    $content = $document->content ?? [];
    $members = $cycle->memberships->where('status', 'active');
    $officers = $cycle->officers->sortBy('sort_order');
    $title = match($document->document_type) {
        'application' => 'APPLICATION LETTER FOR RECOGNITION',
        'constitution' => 'CONSTITUTION AND BY-LAWS',
        'adviser_acceptance' => 'ACCEPTANCE LETTER OF ADVISERSHIP',
        'officers' => 'OFFICIAL LIST OF OFFICERS',
        'officer_certification' => 'CERTIFICATION OF STUDENT RECORD',
        'class_list' => 'OFFICIAL CLASS LIST',
        'calendar' => 'CALENDAR OF ACTIVITIES',
        'parent_consent' => 'PARENT CONSENT FORM',
        'risk_plan' => 'RISK ASSESSMENT AND PREVENTIVE MEASURES PLAN',
        default => strtoupper(str_replace('_', ' ', $document->document_type)),
    };
@endphp

<div class="header">
    <strong>PHILIPPINE SCIENCE HIGH SCHOOL SYSTEM</strong><br>
    <strong>CAMPUS: CARAGA REGION CAMPUS</strong><br>
    ALTERNATIVE LEARNING PROGRAM (ALP)
    <h1>{{ $title }}</h1>
    <div>S.Y. {{ $cycle->schoolYear->name }}</div>
    <div class="meta">{{ $cycle->program->name }} ({{ $cycle->program->code }})</div>
</div>

@if($document->document_type === 'application')
    <p>{{ data_get($content, 'date', now()->format('F j, Y')) }}</p>
    <p><span class="label">To:</span> Assistant CID Chief for Student Affairs / DSA Chief</p>
    <p>Greetings of integrity, excellence, and service!</p>
    <p>{{ data_get($content, 'background', $cycle->program->nature) }}</p>
    <p>{{ data_get($content, 'purpose') }}</p>
    <p>{{ data_get($content, 'membership_information', 'The program has '.$members->count().' members for the current school year.') }}</p>
    <p>{{ data_get($content, 'commitment') }}</p>
    <p>{{ data_get($content, 'closing') }}</p>
@elseif($document->document_type === 'constitution')
    <h2>ALP Preamble</h2><p>{{ data_get($content, 'preamble') }}</p>
    <h2>Article I. Declaration of the Organization</h2>
    <p><span class="label">Name and Logo:</span> {{ data_get($content, 'name_and_logo', $cycle->program->name) }}</p>
    <p><span class="label">Purpose and Rationale:</span> {{ data_get($content, 'purpose', $cycle->program->nature) }}</p>
    <p><span class="label">Mission:</span> {{ data_get($content, 'mission', $cycle->program->mission) }}</p>
    <p><span class="label">Objectives:</span></p><ul>@foreach((array) data_get($content, 'objectives', $cycle->program->objectives ?? []) as $item)<li>{{ $item }}</li>@endforeach</ul>
    <h2>Article II. Membership</h2><p>{{ data_get($content, 'membership') }}</p>
    <p><strong>No Hazing:</strong> {{ data_get($content, 'no_hazing_provision') ? 'Hazing or initiation in any form is prohibited as a requirement for membership.' : 'Provision not supplied.' }}</p>
    <h2>Article III. Rights and Privileges</h2><p>{{ data_get($content, 'rights') }}</p>
    <h2>Article IV. Executive Board / Officers</h2><p>{{ data_get($content, 'officers') }}</p><p>{{ data_get($content, 'adviser_duties') }}</p>
    <h2>Article V. Meetings</h2><p>{{ data_get($content, 'meetings') }}</p><p>{{ data_get($content, 'absenteeism') }}</p>
    <h2>Article VI. Amendments</h2><p>{{ data_get($content, 'amendments') }}</p>
@elseif($document->document_type === 'adviser_acceptance')
    <p>{{ data_get($content, 'date', now()->format('F j, Y')) }}</p>
    <p><strong>{{ $cycle->adviser?->name }}</strong><br>Prospective ALP Adviser</p>
    <p>Dear Sir/Ma'am:</p><p>Greetings of integrity, excellence, and service!</p>
    <p>{{ data_get($content, 'introduction') }}</p><p>{{ data_get($content, 'purpose') }}</p><p>{{ data_get($content, 'closing') }}</p>
@elseif($document->document_type === 'officers')
    <table><thead><tr><th>Name</th><th>Sex</th><th>Grade & Section</th><th>Position</th><th>Registrar Status</th></tr></thead><tbody>
    @foreach($officers as $officer)<tr><td>{{ $officer->membership->student->full_name }}</td><td>{{ $officer->membership->student->sex }}</td><td>{{ $officer->membership->enrollment?->grade_level }} - {{ $officer->membership->enrollment?->section?->sectionname }}</td><td>{{ $officer->position }}</td><td>{{ ucfirst($officer->registrar_status) }}</td></tr>@endforeach
    </tbody></table>
@elseif($document->document_type === 'officer_certification')
    <p>This certifies that the following ALP student officers are in good academic standing and fit to lead {{ $cycle->program->name }}.</p>
    <table><thead><tr><th>Name</th><th>Sex</th><th>Grade & Section</th><th>Position</th><th>Standing / Remarks</th></tr></thead><tbody>
    @foreach($officers as $officer)<tr><td>{{ $officer->membership->student->full_name }}</td><td>{{ $officer->membership->student->sex }}</td><td>{{ $officer->membership->enrollment?->grade_level }} - {{ $officer->membership->enrollment?->section?->sectionname }}</td><td>{{ $officer->position }}</td><td>{{ data_get($officer->academic_standing_snapshot, 'standing', ucfirst($officer->registrar_status)) }}</td></tr>@endforeach
    </tbody></table>
@elseif($document->document_type === 'class_list')
    <table><thead><tr><th style="width:5%">#</th><th>Name</th><th style="width:12%">Sex</th><th>Grade & Section</th><th>Consent</th></tr></thead><tbody>
    @foreach($members as $member)<tr><td>{{ $loop->iteration }}</td><td>{{ $member->student->full_name }}</td><td>{{ $member->student->sex }}</td><td>{{ $member->enrollment?->grade_level }} - {{ $member->enrollment?->section?->sectionname }}</td><td>{{ ucfirst($member->consent_status) }}</td></tr>@endforeach
    </tbody></table>
@elseif($document->document_type === 'calendar')
    <table><thead><tr><th>Date</th><th>Activity</th><th>Learning Outcomes</th><th>Participants</th><th>Venue</th><th>Budget / Resources</th></tr></thead><tbody>
    @foreach($cycle->activities->sortBy('start_date') as $activity)<tr><td>{{ $activity->start_date?->format('M j, Y') }}</td><td>{{ $activity->title }}</td><td>{{ $activity->learning_outcomes }}</td><td>{{ $activity->target_participants }}</td><td>{{ $activity->venue }}</td><td>PHP {{ number_format((float)$activity->budget_amount, 2) }}<br>{{ $activity->resources_needed }}</td></tr>@endforeach
    </tbody></table>
@elseif($document->document_type === 'parent_consent')
    <p>This is the controlled parent-consent template for participation in {{ $cycle->program->name }} during School Year {{ $cycle->schoolYear->name }}.</p>
    <p>{{ data_get($content, 'statement', 'The parent or guardian voluntarily allows the scholar to participate in approved meetings, activities, and supervised learning experiences under this ALP.') }}</p>
    <p>{{ data_get($content, 'acknowledgement', 'The parent or guardian acknowledges the nature and objectives of the program and authorizes the school to monitor and communicate concerns relating to participation.') }}</p>
    <table><tr><th>Active Members</th><th>Consent Received</th><th>Pending / Declined</th></tr><tr><td>{{ $members->count() }}</td><td>{{ $members->where('consent_status','received')->count() }}</td><td>{{ $members->where('consent_status','!=','received')->count() }}</td></tr></table>
@elseif($document->document_type === 'risk_plan')
    <p><span class="label">Background / Nature:</span> {{ data_get($content, 'background', $cycle->program->nature) }}</p>
    <p><span class="label">Identified Hazards:</span> {{ implode(', ', (array)data_get($content, 'hazards', [])) }}</p>
    <p><span class="label">Risk Level:</span> {{ strtoupper(data_get($content, 'risk_level', '')) }}</p>
    <table><thead><tr><th>Identified Risk</th><th>Preventive / Mitigation Measure</th></tr></thead><tbody>@foreach((array)data_get($content, 'mitigations', []) as $row)<tr><td>{{ data_get($row,'risk') }}</td><td>{{ data_get($row,'mitigation') }}</td></tr>@endforeach</tbody></table>
    <p><span class="label">Emergency Contact:</span> {{ data_get($content, 'emergency_contact') }} / {{ data_get($content, 'emergency_mobile') }}</p>
    <p><span class="label">Emergency Procedure:</span> {{ data_get($content, 'emergency_procedure') }}</p>
    <p><span class="label">Venue Inspection:</span> {{ data_get($content, 'venue_inspected') ? 'Completed' : 'Not completed' }} - {{ data_get($content, 'venue_details') }}</p>
    <p><span class="label">Incident Reporting:</span> {{ data_get($content, 'incident_procedure') }}</p>
@endif

<div style="margin-top:24px">
    <div class="signature"><strong>Prepared / Endorsed by:</strong><br><br>{{ $cycle->adviser?->name ?? '____________________' }}<br>ALP Adviser</div>
    <div class="signature"><strong>Reviewed / Noted by:</strong><br><br>{{ $cycle->coordinator?->name ?? '____________________' }}<br>ALP Coordinator</div>
</div>
@if($cycle->approvalSnapshots->isNotEmpty())
<table style="margin-top:20px"><thead><tr><th>Step</th><th>Action</th><th>Signatory</th><th>Date</th></tr></thead><tbody>
@foreach($cycle->approvalSnapshots as $snapshot)<tr><td>{{ str($snapshot->step)->replace('_',' ')->title() }}</td><td>{{ $snapshot->action_label }}</td><td>{{ $snapshot->name_snapshot }}</td><td>{{ $snapshot->signed_at?->format('F j, Y') }}</td></tr>@endforeach
</tbody></table>
@endif
<div class="footer">{{ $document->form_code }}-Ver{{ str_pad($document->version_no, 2, '0', STR_PAD_LEFT) }}-Rev{{ $document->revision_no }} | Generated {{ now()->format('m/d/Y') }} | Controlled when verified in BugSayMis</div>
