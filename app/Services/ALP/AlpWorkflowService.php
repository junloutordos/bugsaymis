<?php

namespace App\Services\ALP;

use App\Models\ALP\AlpActivityLog;
use App\Models\ALP\AlpProgramCycle;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\NotificationService;
use App\Services\SnapshotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AlpWorkflowService
{
    public function __construct(
        private AlpComplianceService $compliance,
        private SnapshotService $snapshots,
        private DigitalSignatureService $signatures,
    ) {}

    public function transition(AlpProgramCycle $cycle, string $action, User $actor, ?string $remarks = null, ?string $pin = null): AlpProgramCycle
    {
        $map = [
            'submit' => ['from' => ['draft', 'returned'], 'to' => 'pending_coordinator', 'step' => 'adviser_submission', 'sequence' => 1, 'snapshot' => 'filed'],
            'review' => ['from' => ['pending_coordinator'], 'to' => 'reviewed', 'step' => 'alp_coordinator', 'sequence' => 2, 'snapshot' => 'reviewed'],
            'recommend' => ['from' => ['reviewed'], 'to' => 'recommended', 'step' => 'acid_student_affairs', 'sequence' => 3, 'snapshot' => 'forwarded'],
            'approve' => ['from' => ['recommended'], 'to' => 'accredited', 'step' => 'campus_director', 'sequence' => 4, 'snapshot' => 'approved'],
            'return' => ['from' => ['pending_coordinator', 'reviewed', 'recommended'], 'to' => 'returned', 'step' => 'returned_for_correction', 'sequence' => 9, 'snapshot' => 'rejected'],
            'close' => ['from' => ['accredited'], 'to' => 'closed', 'step' => 'year_end_close', 'sequence' => 10, 'snapshot' => 'certified'],
        ];

        $transition = $map[$action] ?? null;
        if (! $transition || ! in_array($cycle->status, $transition['from'], true)) {
            throw ValidationException::withMessages(['action' => 'This action is not valid for the current ALP status.']);
        }

        if ($action === 'submit') {
            $deficiencies = $this->compliance->deficiencies($cycle);
            if ($deficiencies) {
                throw ValidationException::withMessages(['compliance' => $deficiencies]);
            }
        }
        if ($action === 'close') {
            $deficiencies = $this->compliance->yearEndDeficiencies($cycle);
            if ($deficiencies) {
                throw ValidationException::withMessages(['compliance' => $deficiencies]);
            }
        }
        if ($action === 'return' && blank($remarks)) {
            throw ValidationException::withMessages(['remarks' => 'Deficiency remarks are required when returning a package.']);
        }

        $this->verifyPinWhenConfigured($actor, $pin);

        DB::transaction(function () use ($cycle, $action, $actor, $remarks, $transition) {
            $dates = match ($action) {
                'submit' => ['submitted_at' => now(), 'deficiency_notes' => null],
                'review' => ['reviewed_at' => now()],
                'recommend' => ['recommended_at' => now()],
                'approve' => ['accredited_at' => now()],
                'return' => ['deficiency_notes' => $remarks],
                'close' => ['closed_at' => now()],
                default => [],
            };
            $cycle->update(['status' => $transition['to'], ...$dates]);
            if ($action === 'approve') {
                $cycle->documents()->where('status', 'submitted')->update(['status' => 'approved', 'approved_at' => now()]);
            }
            if ($action === 'close') {
                $cycle->memberships()->where('status', 'active')->update(['status' => 'completed', 'completed_at' => now()]);
            }
            $this->snapshots->recordApproval($cycle, $transition['step'], $transition['sequence'], $transition['snapshot'], $actor, $remarks ?? '');
            $this->signatures->sign(
                $actor,
                AlpProgramCycle::class,
                $cycle->id,
                "ALP {$cycle->program?->name} - {$transition['to']}",
                json_encode([$cycle->id, $cycle->school_year_id, $transition['to'], $cycle->updated_at?->toISOString()]),
                ['stage' => $transition['step'], 'action' => $action]
            );
            $this->log($cycle, $actor, 'cycle_'.$action, $cycle, ['remarks' => $remarks]);
        });

        $recipient = in_array($action, ['return', 'approve'], true) ? $cycle->adviser : $cycle->coordinator;
        if ($recipient && $recipient->id !== $actor->id) {
            NotificationService::notifyUser(
                $recipient,
                'Alternative Learning Program',
                $cycle->program?->code ?? (string) $cycle->id,
                str($transition['to'])->replace('_', ' ')->title()->toString(),
                route('alp.cycles.show', $cycle),
                $remarks,
            );
        }

        return $cycle->refresh();
    }

    public function log(AlpProgramCycle $cycle, ?User $actor, string $action, object $subject, array $metadata = []): void
    {
        AlpActivityLog::create([
            'alp_program_cycle_id' => $cycle->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id ?? null,
            'metadata' => array_filter($metadata, fn ($value) => $value !== null),
        ]);
    }

    public function verifyPinWhenConfigured(User $actor, ?string $pin): void
    {
        if ($actor->signature_pin && (! $pin || ! $this->signatures->verifyPin($actor, $pin))) {
            throw ValidationException::withMessages(['pin' => 'The digital signature PIN is incorrect.']);
        }
    }
}
