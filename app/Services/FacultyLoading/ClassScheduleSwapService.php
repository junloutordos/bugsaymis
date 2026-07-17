<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\ClassSchedule;
use App\Models\FacultyLoading\ClassScheduleApprovalBatch;
use App\Models\FacultyLoading\ClassScheduleSwapRequest;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassScheduleSwapService
{
    public function __construct(
        private readonly ScheduleValidationService $validation,
        private readonly FullConstraintValidator $constraints,
        private readonly ScienceCorePlacementService $scienceCore,
        private readonly ClassScheduleApprovalService $approvals,
    ) {}

    public function candidates(ClassScheduleSwapRequest $request, int $limit = 20): array
    {
        $source = $request->sourceSchedule?->loadMissing(['faculty:id,name', 'subject', 'section', 'classroom']);
        if (! $source || $source->entry_type !== 'class' || ! $source->section) {
            return [];
        }
        if ($source->session_type !== 'regular' || in_array($source->subject?->subject_type, ['elective', 'science_core'], true)) {
            return [];
        }
        if ($this->facultyIsLocked($source)) {
            return [];
        }

        $results = [];
        $partners = ClassSchedule::with(['faculty:id,name', 'subject', 'section', 'classroom'])
            ->occupying()->classes()
            ->where('academic_term_id', $source->academic_term_id)
            ->whereKeyNot($source->id)
            ->where('session_type', 'regular')
            ->get();

        foreach ($partners as $partner) {
            if (! $partner->section || $this->duration($partner) !== $this->duration($source)
                || $this->facultyIsLocked($partner)
                || in_array($partner->subject?->subject_type, ['elective', 'science_core'], true)) {
                continue;
            }

            $proposal = $this->buildSwapProposal($source, $partner, false);
            $validated = $this->validateProposal($proposal, [$source->id, $partner->id]);
            if ($validated !== null) {
                $results[] = $this->present($request, $source, $partner, $proposal, $validated, 'swap');

                continue;
            }

            if ($source->classroom_id !== $partner->classroom_id) {
                $proposal = $this->buildSwapProposal($source, $partner, true);
                $validated = $this->validateProposal($proposal, [$source->id, $partner->id]);
                if ($validated !== null) {
                    $results[] = $this->present($request, $source, $partner, $proposal, $validated, 'swap_with_rooms');
                }
            }
        }

        foreach (SchedulingConstants::DAYS as $day) {
            foreach (SchedulingConstants::getClassSlots((int) $source->section->levelid, $day) as $slot) {
                if ($this->minutes($slot['start'], $slot['end']) !== $this->duration($source)
                    || ($day === $source->day_of_week && $slot['start'] === substr($source->start_time, 0, 5))) {
                    continue;
                }
                $proposal = [[
                    'schedule_id' => $source->id,
                    'attributes' => [
                        'day_of_week' => $day,
                        'start_time' => $slot['start'],
                        'end_time' => $slot['end'],
                        'classroom_id' => $source->classroom_id,
                    ],
                ]];
                $validated = $this->validateProposal($proposal, [$source->id]);
                if ($validated !== null) {
                    $results[] = $this->present($request, $source, null, $proposal, $validated, 'vacant_slot');
                }
            }
        }

        usort($results, fn ($a, $b) => ($b['score'] <=> $a['score']) ?: strcmp($a['type'], $b['type']));

        return array_slice($results, 0, $limit);
    }

    public function apply(ClassScheduleSwapRequest $request, string $candidateId, User $actor, ?string $pin): string
    {
        return DB::transaction(function () use ($request, $candidateId, $actor, $pin) {
            $request = ClassScheduleSwapRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            $termId = (int) $request->academic_term_id;
            ClassSchedule::where('academic_term_id', $termId)->lockForUpdate()->get(['id']);

            $candidate = collect($this->candidates($request, 100))->firstWhere('id', $candidateId);
            if (! $candidate) {
                throw ValidationException::withMessages(['swap' => 'This swap is no longer available. Refresh the suggestions.']);
            }
            if (ClassScheduleApprovalBatch::where('academic_term_id', $termId)->where('status', 'pending_ocd')->exists()) {
                throw ValidationException::withMessages(['swap' => 'OCD must act on the pending schedule submission first.']);
            }

            $request->update([
                'target_schedule_id' => $candidate['target_schedule_id'],
                'selected_proposal' => $candidate,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $approved = ClassScheduleApprovalBatch::where('academic_term_id', $termId)
                ->where('status', 'approved')->latest('id')->first();
            if ($approved) {
                if (! $pin) {
                    throw ValidationException::withMessages(['pin' => 'Enter your signature PIN to submit this amendment to OCD.']);
                }
                $this->approvals->submitSwapAmendment(
                    $request->academicTerm, $actor, $pin, $candidate['changes'], $request
                );

                return 'Swap amendment submitted to OCD. The official schedule remains unchanged until approval.';
            }

            foreach ($candidate['changes'] as $change) {
                ClassSchedule::whereKey($change['schedule_id'])->update($change['attributes']);
            }
            $request->update([
                'status' => 'applied', 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'applied_at' => now(),
            ]);

            $request->loadMissing(['requester', 'targetSchedule.faculty']);
            NotificationService::notifyUser(
                $request->requester, 'Class Schedule', "Swap Request #{$request->id}",
                'Schedule Swap Applied', route('faculty-loading.my-schedule', ['term_id' => $termId]),
            );
            $partner = $request->targetSchedule?->faculty;
            if ($partner && $partner->id !== $request->requester->id) {
                NotificationService::notifyUser(
                    $partner, 'Class Schedule', "Swap Request #{$request->id}",
                    'Schedule Swap Applied', route('faculty-loading.my-schedule', ['term_id' => $termId]),
                );
            }

            return $candidate['type'] === 'vacant_slot' ? 'Schedule moved to the selected vacant slot.' : 'Schedules swapped successfully.';
        });
    }

    private function buildSwapProposal(ClassSchedule $source, ClassSchedule $partner, bool $swapRooms): array
    {
        return [
            [
                'schedule_id' => $source->id,
                'attributes' => [
                    'day_of_week' => $partner->day_of_week,
                    'start_time' => substr($partner->start_time, 0, 5),
                    'end_time' => substr($partner->end_time, 0, 5),
                    'classroom_id' => $swapRooms ? $partner->classroom_id : $source->classroom_id,
                ],
            ],
            [
                'schedule_id' => $partner->id,
                'attributes' => [
                    'day_of_week' => $source->day_of_week,
                    'start_time' => substr($source->start_time, 0, 5),
                    'end_time' => substr($source->end_time, 0, 5),
                    'classroom_id' => $swapRooms ? $source->classroom_id : $partner->classroom_id,
                ],
            ],
        ];
    }

    private function validateProposal(array $changes, array $excludedIds): ?array
    {
        $warnings = [];
        foreach ($changes as $change) {
            $schedule = ClassSchedule::with(['subject', 'section'])->find($change['schedule_id']);
            if (! $schedule || ! $schedule->section) {
                return null;
            }
            $attributes = $change['attributes'];
            $constant = $this->constraints->checkConstant(
                (int) $schedule->section->levelid,
                $attributes['day_of_week'], $attributes['start_time'], $attributes['end_time']
            );
            if (! $constant['passes']) {
                return null;
            }
            if ($schedule->subject?->subject_type === 'science_core') {
                $science = $this->scienceCore->validateParallelPlacement(
                    (int) $schedule->section->levelid,
                    $attributes['day_of_week'], $attributes['start_time'], $attributes['end_time']
                );
                if (! $science['passes']) {
                    return null;
                }
            }
            $result = $this->validation->validate([
                'faculty_id' => $schedule->user_id,
                'subject_id' => $schedule->subject_id,
                'section_id' => $schedule->section_id,
                'classroom_id' => $attributes['classroom_id'],
                'school_year_id' => $schedule->school_year_id,
                'academic_term_id' => $schedule->academic_term_id,
                'day_of_week' => $attributes['day_of_week'],
                'start_time' => $attributes['start_time'],
                'end_time' => $attributes['end_time'],
            ], $excludedIds);
            if (! $result['valid']) {
                return null;
            }
            $warnings = array_merge($warnings, $result['warnings']);
        }

        return ['warnings' => array_values(array_unique($warnings))];
    }

    private function present(
        ClassScheduleSwapRequest $request,
        ClassSchedule $source,
        ?ClassSchedule $partner,
        array $changes,
        array $validation,
        string $type
    ): array {
        $sourceChange = $changes[0]['attributes'];
        $preferences = $request->preferences ?? [];
        $score = $type === 'swap' ? 100 : ($type === 'swap_with_rooms' ? 90 : 80);
        if (($preferences['day'] ?? null) === $sourceChange['day_of_week']) {
            $score += 20;
        }
        if (! empty($preferences['start_time']) && $sourceChange['start_time'] >= $preferences['start_time']) {
            $score += 5;
        }
        if (! empty($preferences['end_time']) && $sourceChange['end_time'] <= $preferences['end_time']) {
            $score += 5;
        }
        $id = hash('sha256', json_encode([$request->id, $type, $changes]));

        return [
            'id' => $id,
            'type' => $type,
            'score' => $score,
            'target_schedule_id' => $partner?->id,
            'label' => $partner
                ? "Swap with {$partner->subject?->code} - {$partner->faculty?->name}"
                : 'Move to a vacant slot',
            'source' => $this->summary($source),
            'target' => $partner ? $this->summary($partner) : null,
            'source_new' => $sourceChange,
            'target_new' => $changes[1]['attributes'] ?? null,
            'warnings' => $validation['warnings'],
            'changes' => $changes,
        ];
    }

    private function summary(ClassSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'subject' => $schedule->subject?->code ?? $schedule->subject?->name,
            'faculty' => $schedule->faculty?->name,
            'section' => $schedule->section?->sectionname,
            'grade' => $schedule->section?->levelid,
            'room' => $schedule->classroom?->name,
            'day' => $schedule->day_of_week,
            'start' => substr($schedule->start_time, 0, 5),
            'end' => substr($schedule->end_time, 0, 5),
        ];
    }

    private function facultyIsLocked(ClassSchedule $schedule): bool
    {
        return FacultyLoad::where('academic_term_id', $schedule->academic_term_id)
            ->where('user_id', $schedule->user_id)->where('is_locked', true)->exists();
    }

    private function duration(ClassSchedule $schedule): int
    {
        return $this->minutes($schedule->start_time, $schedule->end_time);
    }

    private function minutes(string $start, string $end): int
    {
        [$sh, $sm] = array_map('intval', explode(':', $start));
        [$eh, $em] = array_map('intval', explode(':', $end));

        return (($eh * 60) + $em) - (($sh * 60) + $sm);
    }
}
