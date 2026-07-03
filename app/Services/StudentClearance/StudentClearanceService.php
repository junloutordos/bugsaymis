<?php

namespace App\Services\StudentClearance;

use App\Models\ClassRecord\ClassRecord;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentEnrollment;
use App\Models\ResidenceHall\RhIntern;
use App\Models\Student;
use App\Models\StudentClearance\StudentClearance;
use App\Models\StudentClearance\StudentClearanceActivityLog;
use App\Models\StudentClearance\StudentClearanceItem;
use App\Models\StudentClearance\StudentClearancePeriod;
use App\Models\User;
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

        return $created;
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

    private function officeItems(StudentEnrollment $enrollment, Student $student): Collection
    {
        $grade = (int) $enrollment->grade_level;
        $isIntern = RhIntern::where('student_id', $student->id)
            ->where('school_year_id', $enrollment->school_year_id)
            ->whereIn('status', ['active', 'checked_in'])
            ->exists();

        $items = [
            ['code' => 'bio_lab', 'label' => 'Biology Laboratory', 'group' => 'laboratory', 'permission' => 'students.clearance.sign'],
            ['code' => 'chem_lab', 'label' => 'Chemistry Laboratory', 'group' => 'laboratory', 'permission' => 'students.clearance.sign'],
            ['code' => 'physics_lab', 'label' => 'Physics Laboratory', 'group' => 'laboratory', 'permission' => 'students.clearance.sign'],
            ['code' => 'computer_lab', 'label' => 'Computer Laboratory', 'group' => 'laboratory', 'permission' => 'students.clearance.sign'],
            ['code' => 'dental', 'label' => 'Dental', 'group' => 'administrative', 'permission' => 'health.manage'],
            ['code' => 'medical', 'label' => 'Medical', 'group' => 'administrative', 'permission' => 'health.manage'],
            ['code' => 'guidance', 'label' => 'Guidance', 'group' => 'administrative', 'permission' => 'guidance.manage'],
            ['code' => 'library', 'label' => 'Library', 'group' => 'administrative', 'permission' => 'library.manage'],
            ['code' => 'canteen', 'label' => 'Canteen', 'group' => 'administrative', 'permission' => 'students.clearance.sign'],
            ['code' => 'supply_books', 'label' => 'Supply & Property - Books', 'group' => 'administrative', 'permission' => 'supply.manage'],
            ['code' => 'supply_locker', 'label' => 'Supply & Property - Locker', 'group' => 'administrative', 'permission' => 'property.manage'],
            ['code' => 'cashier', 'label' => 'Cashier', 'group' => 'administrative', 'permission' => 'students.clearance.sign'],
            ['code' => 'discipline', 'label' => 'Discipline Officer', 'group' => 'administrative', 'permission' => 'discipline.manage'],
            ['code' => 'ala_club', 'label' => 'ALA / Club Adviser', 'group' => 'administrative', 'permission' => 'students.clearance.sign'],
            ['code' => 'sg_adviser', 'label' => 'SG Adviser', 'group' => 'administrative', 'permission' => 'students.clearance.sign'],
        ];

        if ($isIntern) {
            $items[] = ['code' => 'residence_hall', 'label' => 'Residence Hall', 'group' => 'administrative', 'permission' => 'rh.interns.manage'];
        }

        if ($grade >= 11) {
            $items[] = ['code' => 'scale_adviser', 'label' => 'SCALE Adviser', 'group' => 'administrative', 'permission' => 'students.clearance.sign'];
        }

        if ($grade === 12) {
            $items[] = ['code' => 'research_teacher', 'label' => 'Research Teacher', 'group' => 'subject', 'permission' => 'students.clearance.subject-sign'];
            $items[] = ['code' => 'cid_chief', 'label' => 'Chief, Curriculum & Instruction Division', 'group' => 'final', 'permission' => 'students.clearance.sign'];
            $items[] = ['code' => 'ssd_chief', 'label' => 'Chief, Student Services Division', 'group' => 'final', 'permission' => 'students.clearance.sign'];
            $items[] = ['code' => 'campus_director', 'label' => 'Campus Director', 'group' => 'final', 'permission' => 'students.clearance.admin'];
        }

        $items[] = [
            'code'       => 'section_adviser',
            'label'      => 'Section / Homeroom Adviser',
            'group'      => 'final',
            'permission' => null,
            'user_id'    => $enrollment->section?->adviser,
        ];
        $items[] = ['code' => 'registrar', 'label' => 'Registrar', 'group' => 'final', 'permission' => 'students.clearance.registrar'];

        return collect($items)->map(fn (array $item, int $index) => [
            'requirement_code'    => 'office:'.$item['code'],
            'requirement_label'   => $item['label'],
            'requirement_type'    => in_array($item['code'], ['section_adviser', 'registrar'], true) ? $item['code'] : 'office',
            'requirement_group'   => $item['group'],
            'assigned_user_id'    => $item['user_id'] ?? null,
            'assigned_permission' => $item['permission'] ?? null,
            'sort_order'          => 500 + $index,
        ]);
    }
}
