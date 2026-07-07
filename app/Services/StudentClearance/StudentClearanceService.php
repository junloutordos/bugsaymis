<?php

namespace App\Services\StudentClearance;

use App\Models\Borrowing;
use App\Models\ClassRecord\ClassRecord;
use App\Models\Discipline\DisciplineCase;
use App\Models\Discipline\DisciplineConfiscatedItem;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentEnrollment;
use App\Models\ResidenceHall\RhFeeLedger;
use App\Models\ResidenceHall\RhIncident;
use App\Models\ResidenceHall\RhIntern;
use App\Models\Student;
use App\Models\StudentClearance\StudentClearance;
use App\Models\StudentClearance\StudentClearanceActivityLog;
use App\Models\StudentClearance\StudentClearanceItem;
use App\Models\StudentClearance\StudentClearancePeriod;
use App\Models\StudentClearance\StudentClearanceRequirementSetting;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StudentClearanceService
{
    public function currentSchoolYearId(): ?int
    {
        return SchoolYear::where('is_current', true)->value('id');
    }

    public function activeOrLatestPeriod(?int $schoolYearId = null): ?StudentClearancePeriod
    {
        $schoolYearId ??= $this->currentSchoolYearId();

        if (! $schoolYearId) {
            return null;
        }

        return StudentClearancePeriod::with('schoolYear:id,name,is_current')
            ->where('school_year_id', $schoolYearId)
            ->orderByRaw("FIELD(status, 'open', 'draft', 'closed', 'archived')")
            ->latest('id')
            ->first();
    }

    /**
     * The student's own clearance for a period, eager-loaded for
     * student-facing display (web portal and mobile API).
     */
    public function clearanceForStudent(StudentClearancePeriod $period, int $studentId): ?StudentClearance
    {
        return StudentClearance::with([
            'period.schoolYear:id,name,is_current',
            'section:id,sectionname,levelid',
            'adviser:id,name',
            'items.assignedUser:id,name',
            'items.signer:id,name',
        ])
            ->where('student_clearance_period_id', $period->id)
            ->where('student_id', $studentId)
            ->first();
    }

    /**
     * Student-facing serialization of a clearance (shared by the web
     * portal Clearance page and the mobile API).
     */
    public function serializeForStudent(StudentClearance $clearance): array
    {
        return [
            'id'           => $clearance->id,
            'status'       => $clearance->status,
            'grade_level'  => $clearance->grade_level,
            'section_name' => $clearance->section?->sectionname,
            'adviser_name' => $clearance->adviser?->name,
            'finalized_at' => $clearance->finalized_at?->format('Y-m-d H:i'),
            'items'        => $clearance->items->map(fn ($item) => [
                'id'                => $item->id,
                'requirement_label' => $item->requirement_label,
                'requirement_type'  => $item->requirement_type,
                'requirement_group' => $item->requirement_group,
                'status'            => $item->status,
                'remarks'           => $item->remarks,
                'accountability'    => $item->accountability,
                'blocker_summary'   => $item->blocker_summary,
                'assigned_to'       => $item->assignedUser?->name,
                'signed_by'         => $item->signer?->name,
                'signed_at'         => $item->signed_at?->format('Y-m-d H:i'),
            ])->values(),
        ];
    }

    public function createPeriod(array $data, User $actor): StudentClearancePeriod
    {
        return StudentClearancePeriod::create([
            'school_year_id'      => $data['school_year_id'],
            'title'               => $data['title'],
            'opens_at'            => $data['opens_at'] ?? null,
            'closes_at'           => $data['closes_at'] ?? null,
            'status'              => $data['status'] ?? 'draft',
            'target_grade_levels' => $data['target_grade_levels'] ?? range(7, 12),
            'created_by'          => $actor->id,
        ]);
    }

    public function generateForPeriod(StudentClearancePeriod $period, User $actor): array
    {
        $targetGrades = collect($period->target_grade_levels ?: range(7, 12))
            ->map(fn ($grade) => (int) $grade)
            ->values()
            ->all();

        $enrollments = StudentEnrollment::query()
            ->where('school_year_id', $period->school_year_id)
            ->where('status', 'enrolled')
            ->when($targetGrades, fn ($q) => $q->whereIn('grade_level', $targetGrades))
            ->with('section:id,sectionname,levelid,adviser,school_year_id,syid')
            ->get();

        $students = Student::whereIn('id', $enrollments->pluck('student_id')->unique())
            ->get(['id', 'firstname', 'lastname', 'middlename', 'pisaysystemID'])
            ->keyBy('id');

        $generated = 0;
        $itemsCreated = 0;

        DB::transaction(function () use ($period, $actor, $enrollments, $students, &$generated, &$itemsCreated) {
            foreach ($enrollments as $enrollment) {
                $student = $students->get($enrollment->student_id);

                if (! $student) {
                    continue;
                }

                $clearance = StudentClearance::firstOrCreate(
                    [
                        'student_clearance_period_id' => $period->id,
                        'student_id'                  => $student->id,
                    ],
                    [
                        'school_year_id'        => $period->school_year_id,
                        'pisaysystem_id'        => $student->pisaysystemID,
                        'student_enrollment_id' => $enrollment->id,
                        'section_id'            => $enrollment->section_id,
                        'grade_level'           => $enrollment->grade_level,
                        'adviser_id'            => $enrollment->section?->adviser,
                        'status'                => 'open',
                    ]
                );

                if (! $clearance->wasRecentlyCreated) {
                    $clearance->update([
                        'school_year_id'        => $period->school_year_id,
                        'pisaysystem_id'        => $student->pisaysystemID,
                        'student_enrollment_id' => $enrollment->id,
                        'section_id'            => $enrollment->section_id,
                        'grade_level'           => $enrollment->grade_level,
                        'adviser_id'            => $enrollment->section?->adviser,
                    ]);
                }

                $itemsCreated += $this->syncGeneratedItems($clearance, $enrollment, $student);
                $generated++;

                $this->log($clearance, null, $actor, 'generated', [
                    'period_id' => $period->id,
                ]);
            }
        });

        return [
            'clearances' => $generated,
            'items'      => $itemsCreated,
        ];
    }

    public function syncGeneratedItems(StudentClearance $clearance, StudentEnrollment $enrollment, Student $student): int
    {
        $items = collect()
            ->merge($this->subjectItems($enrollment, $student))
            ->merge($this->officeItems($enrollment, $student))
            ->values();

        $created = 0;

        foreach ($items as $item) {
            $model = StudentClearanceItem::firstOrCreate(
                [
                    'student_clearance_id' => $clearance->id,
                    'requirement_code'     => $item['requirement_code'],
                ],
                [
                    'requirement_label'   => $item['requirement_label'],
                    'requirement_type'    => $item['requirement_type'],
                    'requirement_group'   => $item['requirement_group'],
                    'subject_id'          => $item['subject_id'] ?? null,
                    'class_record_id'     => $item['class_record_id'] ?? null,
                    'load_assignment_id'  => $item['load_assignment_id'] ?? null,
                    'assigned_user_id'    => $item['assigned_user_id'] ?? null,
                    'assigned_permission' => $item['assigned_permission'] ?? null,
                    'sort_order'          => $item['sort_order'] ?? 0,
                    'status'              => 'pending',
                ]
            );

            if ($model->wasRecentlyCreated) {
                $created++;
                $this->notifyAssignedUser($model, $student);
            } else {
                $model->fill([
                    'requirement_label'   => $item['requirement_label'],
                    'requirement_type'    => $item['requirement_type'],
                    'requirement_group'   => $item['requirement_group'],
                    'subject_id'          => $item['subject_id'] ?? null,
                    'class_record_id'     => $item['class_record_id'] ?? null,
                    'load_assignment_id'  => $item['load_assignment_id'] ?? null,
                    'assigned_user_id'    => $item['assigned_user_id'] ?? null,
                    'assigned_permission' => $item['assigned_permission'] ?? null,
                    'sort_order'          => $item['sort_order'] ?? 0,
                ])->save();
            }
        }

        $this->applyAdvisoryBlockers($clearance, $enrollment, $student);
        $this->refreshClearanceStatus($clearance);

        return $created;
    }

    public function applyAdvisoryBlockers(StudentClearance $clearance, StudentEnrollment $enrollment, Student $student): void
    {
        $blockers = $this->advisoryBlockers($enrollment, $student);

        $clearance->items()->get()->each(function (StudentClearanceItem $item) use ($blockers) {
            $itemBlockers = $blockers[$item->requirement_code] ?? [];

            if ($itemBlockers === []) {
                if ($item->blocker_metadata && $item->status === 'hold' && ! $item->signed_by) {
                    $item->update([
                        'status'             => 'pending',
                        'accountability'     => null,
                        'remarks'            => null,
                        'blocker_summary'    => null,
                        'blocker_metadata'   => null,
                        'blocker_checked_at' => now(),
                    ]);
                } elseif ($item->blocker_metadata) {
                    $item->update([
                        'blocker_summary'    => null,
                        'blocker_metadata'   => null,
                        'blocker_checked_at' => now(),
                    ]);
                }

                return;
            }

            $summary = collect($itemBlockers)->pluck('summary')->implode("\n");

            $updates = [
                'blocker_summary'    => $summary,
                'blocker_metadata'   => $itemBlockers,
                'blocker_checked_at' => now(),
            ];

            if (in_array($item->status, ['pending', 'hold'], true) && ! $item->signed_by) {
                $updates['status'] = 'hold';
                $updates['accountability'] = $summary;
                $updates['remarks'] = 'Advisory blocker detected by system sync. The assigned signatory may clear, waive, or keep this on hold after review.';
            }

            $item->update($updates);
        });
    }

    public function canActOnItem(User $user, StudentClearanceItem $item): bool
    {
        if ($user->isSuperAdmin() || $user->hasAnyPermission([
            'students.clearance.manage',
            'students.clearance.admin',
            'students.clearance.registrar',
        ])) {
            return true;
        }

        if ($item->assigned_user_id && $item->assigned_user_id === $user->id) {
            return true;
        }

        return $item->assigned_permission && $user->hasPermission($item->assigned_permission);
    }

    public function updateItem(StudentClearanceItem $item, User $actor, array $data): StudentClearanceItem
    {
        $status = $data['status'];

        $item->update([
            'status'         => $status,
            'remarks'        => $data['remarks'] ?? null,
            'accountability' => $data['accountability'] ?? null,
            'signed_by'      => in_array($status, ['cleared', 'waived', 'not_applicable'], true) ? $actor->id : null,
            'signed_at'      => in_array($status, ['cleared', 'waived', 'not_applicable'], true) ? now() : null,
        ]);

        $this->refreshClearanceStatus($item->clearance);
        $this->log($item->clearance, $item, $actor, 'item_'.$status, [
            'remarks'        => $item->remarks,
            'accountability' => $item->accountability,
        ]);

        return $item->refresh();
    }

    public function adviserReview(StudentClearance $clearance, User $actor): StudentClearance
    {
        $this->refreshClearanceStatus($clearance);

        abort_if($clearance->status !== 'ready_for_adviser', 422, 'This clearance still has pending or held items.');
        abort_unless($clearance->adviser_id === $actor->id || $actor->hasAnyPermission(['students.clearance.manage', 'students.clearance.registrar']), 403);

        $clearance->update([
            'status'              => 'pending_registrar',
            'adviser_reviewed_by' => $actor->id,
            'adviser_reviewed_at' => now(),
        ]);

        $this->log($clearance, null, $actor, 'adviser_reviewed');

        return $clearance->refresh();
    }

    public function finalize(StudentClearance $clearance, User $actor): StudentClearance
    {
        abort_unless($actor->hasAnyPermission(['students.clearance.registrar', 'students.clearance.manage', 'students.clearance.admin']), 403);
        abort_if(! in_array($clearance->status, ['ready_for_adviser', 'pending_registrar'], true), 422, 'This clearance is not ready for registrar finalization.');

        $clearance->update([
            'status'                => 'cleared',
            'registrar_received_by' => $actor->id,
            'registrar_received_at' => now(),
            'finalized_at'          => now(),
        ]);

        $this->log($clearance, null, $actor, 'finalized');

        return $clearance->refresh();
    }

    public function refreshClearanceStatus(StudentClearance $clearance): void
    {
        if (in_array($clearance->status, ['cleared', 'archived'], true)) {
            return;
        }

        $statuses = $clearance->items()->pluck('status');

        if ($statuses->contains(fn ($status) => in_array($status, ['hold', 'returned'], true))) {
            $clearance->update(['status' => 'with_accountability']);
            return;
        }

        if ($statuses->isNotEmpty() && $statuses->every(fn ($status) => in_array($status, ['cleared', 'waived', 'not_applicable'], true))) {
            $clearance->update(['status' => 'ready_for_adviser']);
            return;
        }

        $clearance->update(['status' => 'in_progress']);
    }

    public function log(StudentClearance $clearance, ?StudentClearanceItem $item, ?User $actor, string $action, array $metadata = []): void
    {
        StudentClearanceActivityLog::create([
            'student_clearance_id'      => $clearance->id,
            'student_clearance_item_id' => $item?->id,
            'actor_type'                => $actor ? 'user' : 'system',
            'actor_id'                  => $actor?->id,
            'action'                    => $action,
            'metadata'                  => $metadata ?: null,
        ]);
    }

    private function subjectItems(StudentEnrollment $enrollment, Student $student): Collection
    {
        if (! $enrollment->section_id) {
            return collect();
        }

        $records = ClassRecord::query()
            ->where('school_year_id', $enrollment->school_year_id)
            ->where('section_id', $enrollment->section_id)
            ->whereHas('quarters.students', fn ($q) => $q->where('student_id', $student->id)->where('is_active', true))
            ->with(['teacher:id,name', 'subject:id,name,code'])
            ->get();

        if ($records->isNotEmpty()) {
            return $records->map(fn (ClassRecord $record, int $index) => [
                'requirement_code'  => 'subject:class_record:'.$record->id,
                'requirement_label' => $record->subject?->name ?? $record->subject_name,
                'requirement_type'  => 'subject',
                'requirement_group' => 'subject',
                'subject_id'        => $record->subject_id,
                'class_record_id'   => $record->id,
                'assigned_user_id'  => $record->teacher_id,
                'sort_order'        => 100 + $index,
            ]);
        }

        return LoadAssignment::query()
            ->teaching()
            ->where('school_year_id', $enrollment->school_year_id)
            ->where('section_id', $enrollment->section_id)
            ->with(['faculty:id,name', 'subject:id,name,code'])
            ->get()
            ->unique(fn (LoadAssignment $assignment) => ($assignment->subject_id ?: 'none').':'.$assignment->user_id)
            ->values()
            ->map(fn (LoadAssignment $assignment, int $index) => [
                'requirement_code'    => 'subject:load_assignment:'.$assignment->id,
                'requirement_label'   => $assignment->subject?->name ?? $assignment->description ?? 'Subject Teacher',
                'requirement_type'    => 'subject',
                'requirement_group'   => 'subject',
                'subject_id'          => $assignment->subject_id,
                'load_assignment_id'  => $assignment->id,
                'assigned_user_id'    => $assignment->user_id,
                'sort_order'          => 100 + $index,
            ]);
    }

    private function advisoryBlockers(StudentEnrollment $enrollment, Student $student): array
    {
        $blockers = [];

        $openBorrowings = Borrowing::where(function ($query) {
                $query->where('borrower_type', Student::class)
                    ->orWhere('borrower_type', 'student')
                    ->orWhere('borrower_type', 'Student');
            })
            ->where('borrower_id', $student->id)
            ->whereNull('return_date')
            ->where('status', 'Borrowed')
            ->count();

        if ($openBorrowings > 0) {
            $blockers['office:library'][] = [
                'source'  => 'library_borrowings',
                'count'   => $openBorrowings,
                'summary' => "{$openBorrowings} active library borrowing(s) with no return date.",
            ];
        }

        $openDisciplineCases = DisciplineCase::where('student_id', $student->id)
            ->where('school_year_id', $enrollment->school_year_id)
            ->whereNotIn('status', ['resolved', 'dismissed', 'cancelled'])
            ->count();

        if ($openDisciplineCases > 0) {
            $blockers['office:discipline'][] = [
                'source'  => 'discipline_cases',
                'count'   => $openDisciplineCases,
                'summary' => "{$openDisciplineCases} open discipline case(s) for this school year.",
            ];
        }

        $heldConfiscatedItems = DisciplineConfiscatedItem::where('student_id', $student->id)
            ->where('status', 'held')
            ->count();

        if ($heldConfiscatedItems > 0) {
            $blockers['office:discipline'][] = [
                'source'  => 'discipline_confiscated_items',
                'count'   => $heldConfiscatedItems,
                'summary' => "{$heldConfiscatedItems} held confiscated item(s) pending claim or disposition.",
            ];
        }

        $rhIntern = RhIntern::where('student_id', $student->id)
            ->where('school_year_id', $enrollment->school_year_id)
            ->whereIn('status', ['active', 'suspended'])
            ->first();

        if ($rhIntern) {
            $unpaidFees = RhFeeLedger::where('rh_intern_id', $rhIntern->id)
                ->where('is_paid', false)
                ->count();
            $openIncidents = RhIncident::where('rh_intern_id', $rhIntern->id)
                ->where('status', 'open')
                ->count();

            if ($unpaidFees > 0 || $openIncidents > 0) {
                $parts = [];
                if ($unpaidFees > 0) {
                    $parts[] = "{$unpaidFees} unpaid RH fee ledger item(s)";
                }
                if ($openIncidents > 0) {
                    $parts[] = "{$openIncidents} open RH incident(s)";
                }

                $blockers['office:residence_hall'][] = [
                    'source'  => 'residence_hall',
                    'count'   => $unpaidFees + $openIncidents,
                    'summary' => implode('; ', $parts).'.',
                ];
            }
        }

        $medicalSections = ['allergies', 'immunizations', 'medical_history', 'vitamins'];
        $submittedMedical = DB::table('student_form_submissions')
            ->where('pisaysystemID', $student->pisaysystemID)
            ->where('school_year_id', $enrollment->school_year_id)
            ->whereIn('section', $medicalSections)
            ->pluck('section')
            ->all();
        $missingMedical = array_values(array_diff($medicalSections, $submittedMedical));

        if ($missingMedical !== []) {
            $blockers['office:medical'][] = [
                'source'   => 'student_portal_medical',
                'sections' => $missingMedical,
                'summary'  => 'Missing student portal medical section(s): '.implode(', ', $missingMedical).'.',
            ];
        }

        $profileSections = ['academic', 'activities', 'social', 'career', 'residence', 'health'];
        $submittedProfile = DB::table('student_form_submissions')
            ->where('pisaysystemID', $student->pisaysystemID)
            ->where('school_year_id', $enrollment->school_year_id)
            ->whereIn('section', $profileSections)
            ->pluck('section')
            ->all();
        $missingProfile = array_values(array_diff($profileSections, $submittedProfile));

        if ($missingProfile !== []) {
            $blockers['office:registrar'][] = [
                'source'   => 'student_portal_profile',
                'sections' => $missingProfile,
                'summary'  => 'Missing student portal profile section(s): '.implode(', ', $missingProfile).'.',
            ];
        }

        $standing = StudentAcademicStanding::where('student_id', $student->id)
            ->where('school_year_id', $enrollment->school_year_id)
            ->first();

        if ($standing && (float) $standing->gwa > 2.25) {
            $blockers['office:guidance'][] = [
                'source'  => 'academic_standing',
                'gwa'     => (float) $standing->gwa,
                'summary' => 'Guidance conference required because GWA is above 2.25: '.$standing->gwa.'.',
            ];
        }

        return $blockers;
    }

    private function officeItems(StudentEnrollment $enrollment, Student $student): Collection
    {
        $grade = (int) $enrollment->grade_level;
        $isIntern = RhIntern::where('student_id', $student->id)
            ->where('school_year_id', $enrollment->school_year_id)
            ->whereIn('status', ['active', 'checked_in'])
            ->exists();

        return StudentClearanceRequirementSetting::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('requirement_label')
            ->get()
            ->filter(function (StudentClearanceRequirementSetting $setting) use ($grade, $isIntern) {
                if ($setting->intern_only && ! $isIntern) {
                    return false;
                }

                $gradeLevels = $setting->applies_grade_levels ?: [];
                return $gradeLevels === [] || in_array($grade, array_map('intval', $gradeLevels), true);
            })
            ->values()
            ->map(function (StudentClearanceRequirementSetting $setting) use ($enrollment) {
                $assignedUserId = $setting->requirement_type === 'section_adviser'
                    ? $enrollment->section?->adviser
                    : $setting->assigned_user_id;

                return [
                    'requirement_code'    => $setting->requirement_code,
                    'requirement_label'   => $setting->requirement_label,
                    'requirement_type'    => $setting->requirement_type,
                    'requirement_group'   => $setting->requirement_group,
                    'assigned_user_id'    => $assignedUserId,
                    'assigned_permission' => $setting->assigned_permission,
                    'sort_order'          => $setting->sort_order,
                ];
            });
    }

    private function notifyAssignedUser(StudentClearanceItem $item, Student $student): void
    {
        if (! $item->assigned_user_id) {
            return;
        }

        $user = User::whereKey($item->assigned_user_id)->first();

        if (! $user) {
            return;
        }

        NotificationService::notifyUser(
            $user,
            'Student Clearance',
            $student->full_name,
            'Signature requested',
            route('student-clearance.queue'),
            $item->requirement_label
        );
    }
}
