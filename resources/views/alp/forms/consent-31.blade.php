@extends('alp.forms._layout')
@section('content')
@include('alp.forms._header', ['title' => 'PARENT CONSENT FORM'])

<p class="field-line">DATE: <span class="fill">{{ now()->format('F j, Y') }}</span></p>
<p class="field-line">NAME OF STUDENT: <span class="fill">{{ $membership->student->full_name }}</span></p>
<p class="field-line">GRADE AND SECTION: <span class="fill">{{ $membership->enrollment?->grade_level }} - {{ $membership->enrollment?->section?->sectionname }}</span></p>
<p class="field-line">TITLE OF ACTIVITY: <span class="fill">{{ $activity?->title }}</span></p>
<p class="field-line">VENUE: <span class="fill">{{ $activity?->venue }}</span></p>
<p class="field-line">SCHEDULE: <span class="fill">{{ $activity && $activity->start_date ? $activity->start_date->format('M j, Y').($activity->end_date && ! $activity->end_date->equalTo($activity->start_date) ? ' - '.$activity->end_date->format('M j, Y') : '') : '' }}</span></p>

<p>This is to attest that I voluntarily allow my child to participate in all activities, meetings, and learning experiences under {{ $cycle->program->name }} for School Year {{ $cycle->schoolYear->name }}.</p>

<p>I acknowledge that each Alternative Learning Program supports different learner needs and involves varied learning strategies such as life-skills, project and community-based learning, supervised instructional and developmental activities.</p>

<p>I authorize the school to manage, monitor, and communicate with me regarding any concerns or adjustments needed in relation to the program. I affirm that I am informed about the nature of the ALP and understand its purpose and objective.</p>

<p>I also understand that participation in ALP may include activities inside or outside the classroom/school under the supervision of an authorized school personnel.</p>

<table class="sig-table" style="margin-top:26px">
    <tr>
        <td style="width:100%">
            <div class="sig-line">&nbsp;</div>
            <div class="sig-role">FULL NAME &amp; SIGNATURE OF PARENT/GUARDIAN</div>
        </td>
    </tr>
</table>
<p class="field-line">Name of Child: <span class="fill">{{ $membership->student->full_name }}</span></p>
<p class="field-line">Date: <span class="fill"></span></p>

@include('alp.forms._signatures', ['rows' => [
    [['action' => 'Submitted to:', 'name' => $signatories['adviser'] ?? null, 'role' => 'ALP Adviser', 'date' => true]],
]])
@endsection
