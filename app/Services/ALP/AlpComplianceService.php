<?php

namespace App\Services\ALP;

use App\Models\ALP\AlpProgramCycle;

class AlpComplianceService
{
    public const REQUIRED_DOCUMENTS = [
        'application' => ['code' => 'PSHS-00-F-DSA-24', 'label' => 'Application Letter for Recognition'],
        'constitution' => ['code' => 'PSHS-00-F-DSA-25', 'label' => 'Constitution and By-Laws'],
        'adviser_acceptance' => ['code' => 'PSHS-00-F-DSA-26', 'label' => 'Acceptance Letter of Advisership'],
        'officers' => ['code' => 'PSHS-00-F-DSA-27', 'label' => 'Official List of Officers'],
        'officer_certification' => ['code' => 'PSHS-00-F-DSA-28', 'label' => 'Certification of Student Record'],
        'class_list' => ['code' => 'PSHS-00-F-DSA-29', 'label' => 'Official Class List'],
        'calendar' => ['code' => 'PSHS-00-F-DSA-30', 'label' => 'Calendar of Activities'],
        'parent_consent' => ['code' => 'PSHS-00-F-DSA-31', 'label' => 'Parent Consent Form'],
        'risk_plan' => ['code' => 'PSHS-00-F-DSA-32', 'label' => 'Risk Assessment and Preventive Measures Plan'],
    ];

    public function seedDocuments(AlpProgramCycle $cycle): void
    {
        foreach (self::REQUIRED_DOCUMENTS as $type => $meta) {
            $cycle->documents()->firstOrCreate(
                ['document_type' => $type],
                ['form_code' => $meta['code'], 'version_no' => 2, 'revision_no' => 0, 'content' => $this->defaultContent($type), 'status' => 'draft']
            );
        }
    }

    /**
     * PSHS-00-F-DSA-32's 15-item hazard checklist, verbatim from the controlled
     * form (2-column layout on the printed page; order matches the source PDF).
     */
    public const RISK_CHECKLIST_ITEMS = [
        'Data Privacy Breaches', 'Slips/Trips/Falls', 'Fire/Electrical Hazard', 'Heat-related risks',
        'Equipment Use Risks', 'Legal or compliance risk', 'Crowd/Behavioral Risks', 'Others',
        'Transportation Risks', 'Ergonomic risks (long screen time, poor seating, etc.)',
        'Unsafe outdoor/indoor areas (holes, uneven grounds, poor ventilation, etc.)',
        'Disaster-related risk (earthquake, typhoon, monsoons, etc.)',
        'Medical Issues (allergies, asthma, etc.)', 'Physical/Sport Injury (hitting, kicking, throwing, etc.)',
        'Hazardous substances (cleaning supplies, art materials, etc.)',
    ];

    private function defaultContent(string $type): array
    {
        return match ($type) {
            'application' => ['date' => '', 'background' => '', 'purpose' => '', 'membership_information' => '', 'commitment' => '', 'closing' => 'Respectfully submitted for recognition and accreditation.'],
            'constitution' => [
                'preamble' => '',
                'a1_name_and_logo' => '', 'a1_purpose_rationale' => '', 'a1_mission' => '', 'a1_objectives' => [],
                'a2_eligibility' => '', 'a2_active_members' => '', 'a2_termination' => '', 'no_hazing_provision' => true,
                'a3_equal_opportunity' => 'ALP guarantees a non-discrimination policy regardless of gender, religion, race, or other status.',
                'a3_right_to_petition' => '', 'a3_freedom_of_expression' => '', 'a3_right_to_public_information' => '',
                'a4_members_board' => '', 'a4_role_board' => '', 'a4_appointment_procedure' => '', 'a4_adviser_duties' => '',
                'a5_schedule' => '', 'a5_meeting_process' => '', 'a5_absenteeism' => '',
                'a6_amendments' => 'Amendments require member approval and school review before implementation.',
            ],
            'adviser_acceptance' => ['date' => '', 'introduction' => '', 'purpose' => '', 'closing' => 'I accept the advisership and commit to supervise the program in accordance with PSHS policies and the QMS procedure.'],
            'officers', 'officer_certification', 'class_list', 'calendar' => ['generated_from_system_records' => true],
            'parent_consent' => ['statement' => 'The parent or guardian voluntarily allows the scholar to participate in approved meetings, activities, and supervised learning experiences under this ALP.', 'acknowledgement' => 'The parent or guardian acknowledges the nature and objectives of the program and authorizes the school to monitor and communicate concerns relating to participation.'],
            'risk_plan' => [
                'nature_and_purpose' => '',
                'hazards' => [], 'other_hazard' => '',
                'risk_level' => 'low',
                'mitigations' => [['risk' => '', 'mitigation' => '']],
                'participants_with_medical_needs' => '',
                'emergency_contact' => '', 'emergency_mobile' => '', 'school_clinic_nurse' => '', 'clinic_contact_information' => '',
                'emergency_procedure' => '',
                'venue_inspected' => false, 'venue_details' => '',
                'incident_person_responsible' => '', 'incident_submit_to' => 'Report incidents immediately to the adviser and appropriate school authority.',
            ],
            default => [],
        };
    }

    public function checklist(AlpProgramCycle $cycle): array
    {
        $cycle->loadMissing(['memberships.enrollment', 'officers', 'documents', 'activities']);
        $activeMembers = $cycle->memberships->where('status', 'active');
        $certifiedOfficers = $cycle->officers->where('registrar_status', 'certified');
        $constitution = $cycle->documents->firstWhere('document_type', 'constitution');
        $documentItems = collect(self::REQUIRED_DOCUMENTS)->map(function ($meta, $type) use ($cycle) {
            $document = $cycle->documents->firstWhere('document_type', $type);

            return [
                'key' => 'document:'.$type,
                'label' => $meta['label'],
                'complete' => $document && in_array($document->status, ['submitted', 'approved'], true),
                'status' => $document?->status ?? 'missing',
            ];
        })->values();

        return collect([
            ['key' => 'adviser', 'label' => 'At least one designated faculty adviser', 'complete' => (bool) $cycle->adviser_id],
            ['key' => 'membership_min', 'label' => 'At least 15 active members', 'complete' => $activeMembers->count() >= 15],
            ['key' => 'membership_max', 'label' => 'No more than 40 active members', 'complete' => $activeMembers->count() <= 40],
            ['key' => 'officers', 'label' => 'At least one class officer', 'complete' => $cycle->officers->isNotEmpty()],
            ['key' => 'officer_certification', 'label' => 'All officers certified by the Registrar', 'complete' => $cycle->officers->isNotEmpty() && $certifiedOfficers->count() === $cycle->officers->count()],
            ['key' => 'no_hazing', 'label' => 'Constitution contains the mandatory no-hazing provision', 'complete' => (bool) data_get($constitution?->content, 'no_hazing_provision')],
            ['key' => 'consent', 'label' => 'Parent consent recorded for every active member', 'complete' => $activeMembers->isNotEmpty() && $activeMembers->every(fn ($m) => $m->consent_status === 'received')],
            ['key' => 'calendar_activity', 'label' => 'At least one planned activity', 'complete' => $cycle->activities->isNotEmpty()],
        ])->merge($documentItems)->values()->all();
    }

    public function deficiencies(AlpProgramCycle $cycle): array
    {
        return collect($this->checklist($cycle))->where('complete', false)->pluck('label')->values()->all();
    }

    public function yearEndDeficiencies(AlpProgramCycle $cycle): array
    {
        $cycle->loadMissing(['activities', 'reports']);
        $completedTypes = $cycle->activities->where('status', 'completed')->pluck('activity_type');
        $missing = [];
        if (! $completedTypes->contains(fn ($type) => in_array($type, ['major', 'community_service'], true))) {
            $missing[] = 'No completed major or community-service activity.';
        }
        foreach (['accomplishment', 'attendance_summary', 'financial', 'adviser_evaluation'] as $type) {
            if (! $cycle->reports->where('report_type', $type)->contains(fn ($r) => in_array($r->status, ['submitted', 'approved'], true))) {
                $missing[] = 'Missing submitted '.str_replace('_', ' ', $type).' report.';
            }
        }

        return $missing;
    }
}
