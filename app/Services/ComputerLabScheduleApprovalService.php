<?php

namespace App\Services;

use App\Models\ComputerLabBooking;
use App\Models\ComputerLabScheduleApproval;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Term-wide approval workflow for the Computer Laboratory schedule, covering
 * every computer laboratory in one submission. Mirrors
 * ClassScheduleApprovalService's submit/approve/return shape but is scoped
 * to computer-lab bookings and has a fixed preparer:
 *
 *   Prepared by  — the Science Research Assistant assigned to the Computer
 *                  Laboratory (San Miguel, Alexis Dave M., user id 5).
 *   Approved by  — the CID Chief (resolved by role).
 */
class ComputerLabScheduleApprovalService
{
    /** Only this account may prepare/submit the computer laboratory schedule. */
    public const PREPARER_USER_ID = 5;

    public function __construct(private readonly DigitalSignatureService $signatures) {}

    public function submit(AcademicTerm $term, User $preparer, string $pin): ComputerLabScheduleApproval
    {
        $this->assertIsPreparer($preparer);
        $this->requireSigningIdentity($preparer, $pin);

        return DB::transaction(function () use ($term, $preparer) {
            $existing = ComputerLabScheduleApproval::where('academic_term_id', $term->id)
                ->whereIn('status', ['pending_approval', 'approved'])
                ->lockForUpdate()->first();
            if ($existing) {
                throw ValidationException::withMessages([
                    'schedule' => 'This term already has a pending or approved computer laboratory schedule submission.',
                ]);
            }

            $snapshot = $this->snapshot($term->id);
            if ($snapshot === []) {
                throw ValidationException::withMessages(['schedule' => 'There are no computer laboratory bookings to submit.']);
            }

            $encoded = json_encode($snapshot, JSON_UNESCAPED_SLASHES);
            $approval = ComputerLabScheduleApproval::create([
                'academic_term_id' => $term->id,
                'status' => 'pending_approval',
                'schedule_snapshot' => $snapshot,
                'schedule_hash' => hash('sha256', $encoded),
                'submitted_by' => $preparer->id,
                'submitted_at' => now(),
            ]);

            $this->signatures->sign(
                $preparer,
                ComputerLabScheduleApproval::class,
                $approval->id,
                "Computer Laboratory Schedule - {$term->full_label}",
                $encoded,
                ['stage' => 'prepared', 'signature_path' => $preparer->electronic_signature],
            );

            User::whereHas('roles', fn ($query) => $query->where('name', 'CID Chief'))
                ->where('status', '<>', 'inactive')
                ->get()
                ->each(fn (User $user) => NotificationService::notifyUser(
                    $user,
                    'Computer Laboratory Schedule',
                    "Schedule #{$approval->id}",
                    'Pending CID Chief Approval',
                    route('approvals.inbox'),
                    $term->full_label,
                ));

            return $approval;
        });
    }

    public function approve(ComputerLabScheduleApproval $approval, User $cidChief, string $pin): void
    {
        $this->assertIsCidChief($cidChief);
        $this->requireSigningIdentity($cidChief, $pin);

        DB::transaction(function () use ($approval, $cidChief) {
            $locked = ComputerLabScheduleApproval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_approval') {
                throw ValidationException::withMessages(['schedule' => 'This computer laboratory schedule submission has already been acted upon.']);
            }

            $current = $this->snapshot($locked->academic_term_id);
            $currentEncoded = json_encode($current, JSON_UNESCAPED_SLASHES);
            if (! hash_equals($locked->schedule_hash, hash('sha256', $currentEncoded))) {
                throw ValidationException::withMessages(['schedule' => 'The computer laboratory schedule changed after submission and cannot be approved.']);
            }

            $this->signatures->sign(
                $cidChief,
                ComputerLabScheduleApproval::class,
                $locked->id,
                "Computer Laboratory Schedule - {$locked->academicTerm->full_label}",
                $currentEncoded,
                ['stage' => 'approved', 'signature_path' => $cidChief->electronic_signature],
            );

            $locked->update(['status' => 'approved', 'approved_by' => $cidChief->id, 'approved_at' => now()]);

            NotificationService::notifyUser(
                $locked->submitter,
                'Computer Laboratory Schedule',
                "Schedule #{$locked->id}",
                'Approved by CID Chief',
                route('computer-labs.index', ['term_id' => $locked->academic_term_id]),
            );
        });
    }

    public function returnForRevision(ComputerLabScheduleApproval $approval, User $cidChief, string $reason): void
    {
        $this->assertIsCidChief($cidChief);

        DB::transaction(function () use ($approval, $cidChief, $reason) {
            $locked = ComputerLabScheduleApproval::whereKey($approval->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'pending_approval') {
                throw ValidationException::withMessages(['schedule' => 'This computer laboratory schedule submission has already been acted upon.']);
            }

            $locked->update([
                'status' => 'returned',
                'returned_by' => $cidChief->id,
                'returned_at' => now(),
                'return_remarks' => $reason,
            ]);

            NotificationService::notifyUser(
                $locked->submitter,
                'Computer Laboratory Schedule',
                "Schedule #{$locked->id}",
                'Returned for Revision',
                route('computer-labs.index', ['term_id' => $locked->academic_term_id]),
                $reason,
            );
        });
    }

    public static function latestFor(int $termId): ?ComputerLabScheduleApproval
    {
        return ComputerLabScheduleApproval::where('academic_term_id', $termId)->latest('id')->first();
    }

    private function assertIsPreparer(User $user): void
    {
        if ((int) $user->id !== self::PREPARER_USER_ID && ! $user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'schedule' => 'Only the Science Research Assistant assigned to the Computer Laboratory may prepare this schedule for approval.',
            ]);
        }
    }

    private function assertIsCidChief(User $user): void
    {
        if (! $user->hasRole('CID Chief') && ! $user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'schedule' => 'Only the CID Chief may approve this schedule.',
            ]);
        }
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

    /** All computer-lab bookings that occupy a slot, across every laboratory, for this term. */
    private function snapshot(int $termId): array
    {
        return ComputerLabBooking::where('academic_term_id', $termId)
            ->occupying()
            ->orderBy('room_id')
            ->orderBy('id')
            ->get([
                'id', 'room_id', 'class_schedule_id', 'academic_term_id', 'booking_date',
                'day_of_week', 'start_time', 'end_time', 'booking_type', 'title', 'status',
            ])
            ->map(fn ($row) => $row->getAttributes())
            ->values()
            ->all();
    }
}
