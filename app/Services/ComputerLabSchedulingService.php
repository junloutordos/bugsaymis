<?php

namespace App\Services;

use App\Models\ComputerLabBooking;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ClassSchedule;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ComputerLabSchedulingService
{
    /**
     * Mirror priority subjects from the official timetable into the separate
     * computer-lab calendar. Official class rows are never edited here.
     *
     * @return array{confirmed:int,unassigned:int,displaced:int,total:int}
     */
    public function synchronizeTerm(int $termId): array
    {
        return DB::transaction(function () use ($termId) {
            $term = AcademicTerm::findOrFail($termId);
            $labs = Room::where('room_type', 'Computer Laboratory')
                ->orderBy('name')
                ->lockForUpdate()
                ->get(['id', 'name', 'capacity']);

            $schedules = ClassSchedule::query()
                ->with(['subject:id,code,name,requires_computer_lab', 'faculty:id,name', 'section:id,sectionname'])
                ->where('academic_term_id', $termId)
                ->occupying()
                ->classes()
                ->whereHas('subject', fn ($query) => $query->where('requires_computer_lab', true))
                ->orderByRaw("CASE day_of_week WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END")
                ->orderBy('start_time')
                ->orderBy('end_time')
                ->orderBy('subject_id')
                ->orderBy('section_id')
                ->orderBy('id')
                ->get();

            $scheduleIds = $schedules->pluck('id');
            $stale = ComputerLabBooking::where('booking_type', 'priority_class')
                ->where('academic_term_id', $termId);
            if ($scheduleIds->isEmpty()) {
                $stale->delete();
            } else {
                $stale->whereNotIn('class_schedule_id', $scheduleIds)->delete();
            }

            $existing = ComputerLabBooking::whereIn('class_schedule_id', $scheduleIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('class_schedule_id');

            $labIds = $labs->pluck('id')->map(fn ($id) => (int) $id)->all();
            $assignments = [];
            $occupied = [];

            // Preserve valid lab choices before allocating any newly-created rows.
            foreach ($schedules as $schedule) {
                $roomId = (int) ($existing->get($schedule->id)?->room_id ?? 0);
                if ($roomId && in_array($roomId, $labIds, true)
                    && ! $this->overlapsAllocated($occupied, $roomId, $schedule)) {
                    $assignments[$schedule->id] = $roomId;
                    $occupied[] = $this->slot($roomId, $schedule);
                }
            }

            foreach ($schedules as $schedule) {
                if (array_key_exists($schedule->id, $assignments)) {
                    continue;
                }

                $roomId = collect($labIds)->first(
                    fn ($candidate) => ! $this->overlapsAllocated($occupied, $candidate, $schedule)
                );
                $assignments[$schedule->id] = $roomId;
                if ($roomId) {
                    $occupied[] = $this->slot($roomId, $schedule);
                }
            }

            $confirmed = 0;
            $unassigned = 0;
            foreach ($schedules as $schedule) {
                $roomId = $assignments[$schedule->id];
                $section = $schedule->section?->sectionname ?? 'Unspecified section';
                $title = trim(($schedule->subject?->code ? $schedule->subject->code.' — ' : '')
                    .($schedule->subject?->name ?? $schedule->title ?? 'Class')
                    .' · '.$section);

                ComputerLabBooking::updateOrCreate(
                    ['class_schedule_id' => $schedule->id],
                    [
                        'room_id' => $roomId,
                        'academic_term_id' => $termId,
                        'booking_date' => null,
                        'day_of_week' => $schedule->day_of_week,
                        'start_time' => $schedule->start_time,
                        'end_time' => $schedule->end_time,
                        'booking_type' => 'priority_class',
                        'title' => $title,
                        'purpose' => 'Automatically synchronized from the official class schedule.',
                        'requested_by' => $schedule->user_id,
                        'status' => $roomId ? 'confirmed' : 'unassigned',
                        'decision_by' => null,
                        'decided_at' => null,
                        'conflict_note' => $roomId
                            ? null
                            : 'All computer laboratories are occupied by priority classes during this period.',
                    ],
                );

                $roomId ? $confirmed++ : $unassigned++;
            }

            // A later official timetable change takes precedence over an ad-hoc
            // approval. Return that request to pending instead of double-booking.
            $displaced = 0;
            $approved = ComputerLabBooking::where('booking_type', 'other')
                ->where('status', 'approved')
                ->where('academic_term_id', $termId)
                ->whereNotNull('booking_date')
                ->lockForUpdate()
                ->get();

            foreach ($approved as $booking) {
                if ($this->priorityConflicts($booking)->isEmpty()) {
                    continue;
                }

                $booking->update([
                    'status' => 'pending',
                    'decision_by' => null,
                    'decided_at' => null,
                    'conflict_note' => 'Returned to pending because an official priority class now occupies this laboratory and time.',
                ]);
                $displaced++;
            }

            return [
                'confirmed' => $confirmed,
                'unassigned' => $unassigned,
                'displaced' => $displaced,
                'total' => $schedules->count(),
            ];
        });
    }

    public function approve(ComputerLabBooking $booking, int $userId): void
    {
        DB::transaction(function () use ($booking, $userId) {
            $locked = ComputerLabBooking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            Room::whereKey($locked->room_id)->lockForUpdate()->firstOrFail();

            $conflicts = $this->conflictsFor(
                (int) $locked->room_id,
                Carbon::parse($locked->booking_date),
                $locked->start_time,
                $locked->end_time,
                $locked->id,
            );

            if ($conflicts->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'booking' => 'This laboratory is already occupied during the requested period.',
                ]);
            }

            $locked->update([
                'status' => 'approved',
                'decision_by' => $userId,
                'decided_at' => now(),
                'conflict_note' => null,
            ]);
        });
    }

    public function conflictsFor(
        int $roomId,
        Carbon $date,
        string $startTime,
        string $endTime,
        ?int $ignoreBookingId = null,
    ): Collection {
        return ComputerLabBooking::query()
            ->with(['room:id,name', 'classSchedule.subject:id,code,name', 'classSchedule.section:id,sectionname'])
            ->where('room_id', $roomId)
            ->occupying()
            ->when($ignoreBookingId, fn ($query) => $query->whereKeyNot($ignoreBookingId))
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->where(function ($query) use ($date) {
                $query->where(function ($dated) use ($date) {
                    $dated->where('booking_type', 'other')
                        ->whereDate('booking_date', $date->toDateString());
                })->orWhere(function ($recurring) use ($date) {
                    $recurring->where('booking_type', 'priority_class')
                        ->where('day_of_week', $date->format('l'))
                        ->whereHas('academicTerm', function ($term) use ($date) {
                            $term->whereDate('start_date', '<=', $date->toDateString())
                                ->whereDate('end_date', '>=', $date->toDateString());
                        });
                });
            })
            ->get();
    }

    private function priorityConflicts(ComputerLabBooking $booking): Collection
    {
        return ComputerLabBooking::query()
            ->where('booking_type', 'priority_class')
            ->where('status', 'confirmed')
            ->where('room_id', $booking->room_id)
            ->where('academic_term_id', $booking->academic_term_id)
            ->where('day_of_week', Carbon::parse($booking->booking_date)->format('l'))
            ->where('start_time', '<', $booking->end_time)
            ->where('end_time', '>', $booking->start_time)
            ->get();
    }

    private function overlapsAllocated(array $occupied, int $roomId, ClassSchedule $schedule): bool
    {
        foreach ($occupied as $slot) {
            if ($slot['room_id'] === $roomId
                && $slot['day'] === $schedule->day_of_week
                && $slot['start'] < $schedule->end_time
                && $slot['end'] > $schedule->start_time) {
                return true;
            }
        }

        return false;
    }

    private function slot(int $roomId, ClassSchedule $schedule): array
    {
        return [
            'room_id' => $roomId,
            'day' => $schedule->day_of_week,
            'start' => $schedule->start_time,
            'end' => $schedule->end_time,
        ];
    }
}
