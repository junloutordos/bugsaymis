<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleApprovalBatch;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassScheduleApprovalService
{
    public function __construct(private readonly DigitalSignatureService $signatures) {}

    public function submit(AcademicTerm $term, User $cidChief, string $pin): ClassScheduleApprovalBatch
    {
        $this->requireSigningIdentity($cidChief, $pin);

        return DB::transaction(function () use ($term, $cidChief) {
            $existing = ClassScheduleApprovalBatch::where('academic_term_id', $term->id)
                ->whereIn('status', ['pending_ocd', 'approved'])->lockForUpdate()->first();
            if ($existing) {
                throw ValidationException::withMessages(['schedule' => 'This term already has a pending or approved schedule submission.']);
            }

            $snapshot = $this->snapshot($term->id);
            if ($snapshot === []) {
                throw ValidationException::withMessages(['schedule' => 'There are no schedules to submit.']);
            }
            $conflicts = $this->conflicts($snapshot);
            if ($conflicts !== []) {
                throw ValidationException::withMessages(['schedule' => 'Resolve schedule conflicts before submission: '.implode(' ', $conflicts)]);
            }
            $unplaced = $this->unplacedTeachingSessions($term->id, $snapshot);
            if ($unplaced > 0) {
                throw ValidationException::withMessages([
                    'schedule' => "The complete schedule cannot be submitted while {$unplaced} teaching session(s) remain unplaced.",
                ]);
            }

            $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES);
            $batch = ClassScheduleApprovalBatch::create([
                'academic_term_id' => $term->id,
                'status' => 'pending_ocd',
                'schedule_snapshot' => $snapshot,
                'schedule_hash' => hash('sha256', $encoded),
                'submitted_by' => $cidChief->id,
                'submitted_at' => now(),
            ]);

            $this->signatures->sign(
                $cidChief,
                ClassScheduleApprovalBatch::class,
                $batch->id,
                "Class Schedule - {$term->full_label}",
                $encoded,
                ['stage' => 'prepared', 'signature_path' => $cidChief->electronic_signature],
            );

            User::havingRole('OCD')->where('status', '<>', 'inactive')->get()->each(
                fn (User $user) => NotificationService::notifyUser(
                    $user, 'Class Schedule', "Schedule Batch #{$batch->id}",
                    'Pending OCD Approval', route('approvals.index'), $term->full_label,
                )
            );

            return $batch;
        });
    }

    public function approve(ClassScheduleApprovalBatch $batch, User $ocd, string $pin): void
    {
        $this->requireSigningIdentity($ocd, $pin);

        DB::transaction(function () use ($batch, $ocd) {
            $batch = ClassScheduleApprovalBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'pending_ocd') {
                throw ValidationException::withMessages(['schedule' => 'This schedule submission has already been acted upon.']);
            }

            $current = $this->snapshot($batch->academic_term_id);
            $encoded = json_encode($current, JSON_UNESCAPED_SLASHES);
            if (! hash_equals($batch->schedule_hash, hash('sha256', $encoded))) {
                throw ValidationException::withMessages(['schedule' => 'The schedule changed after submission and cannot be approved.']);
            }

            $this->signatures->sign(
                $ocd,
                ClassScheduleApprovalBatch::class,
                $batch->id,
                "Class Schedule - {$batch->academicTerm->full_label}",
                $encoded,
                ['stage' => 'approved', 'signature_path' => $ocd->electronic_signature],
            );

            ClassSchedule::where('academic_term_id', $batch->academic_term_id)
                ->where('status', 'tentative')->update(['status' => 'active']);
            $batch->update(['status' => 'approved', 'approved_by' => $ocd->id, 'approved_at' => now()]);

            NotificationService::notifyUser(
                $batch->submitter, 'Class Schedule', "Schedule Batch #{$batch->id}",
                'OCD Approved', route('faculty-loading.schedules.index', ['term_id' => $batch->academic_term_id]),
            );
        });
    }

    public function returnForRevision(ClassScheduleApprovalBatch $batch, User $ocd, string $reason): void
    {
        DB::transaction(function () use ($batch, $ocd, $reason) {
            $batch = ClassScheduleApprovalBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status !== 'pending_ocd') {
                throw ValidationException::withMessages(['schedule' => 'This schedule submission has already been acted upon.']);
            }
            $batch->update([
                'status' => 'returned', 'returned_by' => $ocd->id,
                'returned_at' => now(), 'return_remarks' => $reason,
            ]);
            NotificationService::notifyUser(
                $batch->submitter, 'Class Schedule', "Schedule Batch #{$batch->id}",
                'Returned for Revision', route('faculty-loading.schedules.index', ['term_id' => $batch->academic_term_id]), $reason,
            );
        });
    }

    public function conforme(ClassScheduleApprovalBatch $batch, User $faculty, string $pin): void
    {
        $this->requireSigningIdentity($faculty, $pin);
        if ($batch->status !== 'approved') {
            throw ValidationException::withMessages(['schedule' => 'Only an OCD-approved schedule can be signed Conforme.']);
        }
        $hasSchedule = collect($batch->schedule_snapshot)->contains(fn ($row) => (int) ($row['user_id'] ?? 0) === $faculty->id);
        if (! $hasSchedule) {
            throw ValidationException::withMessages(['schedule' => 'You are not included in this schedule batch.']);
        }
        $alreadySigned = $batch->signatures()->where('signer_id', $faculty->id)
            ->where('metadata->stage', 'conforme')->exists();
        if ($alreadySigned) {
            throw ValidationException::withMessages(['schedule' => 'You have already signed this schedule Conforme.']);
        }
        $this->signatures->sign(
            $faculty, ClassScheduleApprovalBatch::class, $batch->id,
            "Individual Faculty Schedule - {$batch->academicTerm->full_label}",
            $batch->schedule_hash.'|'.$faculty->id,
            ['stage' => 'conforme', 'signature_path' => $faculty->electronic_signature],
        );
    }

    public static function termIsLocked(?int $termId): bool
    {
        return $termId && ClassScheduleApprovalBatch::where('academic_term_id', $termId)
            ->whereIn('status', ['pending_ocd', 'approved'])->exists();
    }

    private function requireSigningIdentity(User $user, string $pin): void
    {
        if (! $user->electronic_signature || ! $user->signature_pin) {
            throw ValidationException::withMessages(['pin' => 'Set up your electronic signature and signature PIN before signing.']);
        }
        if (! $this->signatures->verifyPin($user, $pin)) {
            throw ValidationException::withMessages(['pin' => 'The signature PIN is incorrect.']);
        }
    }

    private function snapshot(int $termId): array
    {
        return ClassSchedule::where('academic_term_id', $termId)->occupying()
            ->orderBy('id')->get([
                'id', 'load_assignment_id', 'user_id', 'subject_id', 'section_id', 'classroom_id',
                'school_year_id', 'academic_term_id', 'entry_type', 'session_type', 'title', 'category',
                'day_of_week', 'start_time', 'end_time', 'status', 'remarks',
            ])->map(fn ($row) => $row->getAttributes())->values()->all();
    }

    private function conflicts(array $rows): array
    {
        $messages = [];
        $count = count($rows);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $rows[$i];
                $b = $rows[$j];
                if ($a['day_of_week'] !== $b['day_of_week'] || $a['start_time'] >= $b['end_time'] || $a['end_time'] <= $b['start_time']) {
                    continue;
                }
                foreach (['user_id' => 'faculty', 'classroom_id' => 'room', 'section_id' => 'section'] as $key => $label) {
                    if (! empty($a[$key]) && (int) $a[$key] === (int) $b[$key]) {
                        $messages[] = ucfirst($label)." conflict on {$a['day_of_week']} {$a['start_time']}-{$a['end_time']}.";
                    }
                }
            }
        }

        return array_values(array_unique($messages));
    }

    private function unplacedTeachingSessions(int $termId, array $snapshot): int
    {
        $placed = collect($snapshot)->filter(fn ($row) => ! empty($row['load_assignment_id']))
            ->countBy(fn ($row) => (int) $row['load_assignment_id']);

        return LoadAssignment::with('subject:id,load_units,sessions_per_week')
            ->where('academic_term_id', $termId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('subject_id')
            ->whereNotNull('section_id')
            ->get()
            ->sum(function ($load) use ($placed) {
                $required = max(1, (int) ($load->subject?->sessions_per_week
                    ?: round((float) ($load->subject?->load_units ?? 1))));

                return max(0, $required - (int) ($placed[$load->id] ?? 0));
            });
    }
}
