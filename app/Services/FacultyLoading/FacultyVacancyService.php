<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\FacultyVacancy;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * FacultyVacancyService
 *
 * Manages faculty vacancy tracking:
 *   - Creating vacancies when a position slot is identified
 *   - Marking vacancies filled when a faculty member is assigned
 *   - Cancelling vacancies that are no longer needed
 *   - Querying open slots by unit, term, and position level
 */
class FacultyVacancyService
{
    // ── Creation ──────────────────────────────────────────────────────────────

    /**
     * Open a new vacancy slot.
     *
     * @return array{ok: bool, message: string, vacancy: ?FacultyVacancy}
     */
    public function open(array $data, int $createdBy): array
    {
        $required = ['school_year_id', 'academic_unit_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->fail("Missing required field: {$field}.");
            }
        }

        $vacancy = FacultyVacancy::create([
            'school_year_id'    => $data['school_year_id'],
            'academic_term_id'  => $data['academic_term_id'] ?? null,
            'academic_unit_id'  => $data['academic_unit_id'],
            'sst_position_id'   => $data['sst_position_id']  ?? null,
            'subject_id'        => $data['subject_id']        ?? null,
            'notes'             => $data['notes']             ?? null,
            'status'            => 'open',
            'created_by'        => $createdBy,
        ]);

        return [
            'ok'      => true,
            'message' => 'Vacancy opened successfully.',
            'vacancy' => $vacancy,
        ];
    }

    // ── Resolution ────────────────────────────────────────────────────────────

    /**
     * Mark a vacancy as filled by a specific faculty member.
     */
    public function fill(FacultyVacancy $vacancy, User $faculty): array
    {
        if ($vacancy->status !== 'open') {
            return $this->fail("Vacancy is already '{$vacancy->status}' and cannot be filled.");
        }

        $vacancy->markFilled($faculty);

        return [
            'ok'      => true,
            'message' => "Vacancy filled by {$faculty->name}.",
            'vacancy' => $vacancy->fresh(),
        ];
    }

    /**
     * Cancel a vacancy that is no longer needed.
     */
    public function cancel(FacultyVacancy $vacancy): array
    {
        if ($vacancy->status === 'filled') {
            return $this->fail('Cannot cancel a vacancy that has already been filled.');
        }

        if ($vacancy->status === 'cancelled') {
            return $this->fail('Vacancy is already cancelled.');
        }

        $vacancy->update(['status' => 'cancelled']);

        return ['ok' => true, 'message' => 'Vacancy cancelled.', 'vacancy' => $vacancy->fresh()];
    }

    // ── Queries ───────────────────────────────────────────────────────────────

    /**
     * All open vacancies for a given school year, optionally filtered by unit.
     */
    public function openForYear(int $schoolYearId, ?int $academicUnitId = null): Collection
    {
        return FacultyVacancy::with(['academicUnit', 'sstPosition', 'subject', 'academicTerm'])
            ->where('school_year_id', $schoolYearId)
            ->where('status', 'open')
            ->when($academicUnitId, fn($q) => $q->where('academic_unit_id', $academicUnitId))
            ->orderBy('academic_unit_id')
            ->get();
    }

    /**
     * Summary count of open vacancies per academic unit for a school year.
     * Returns a keyed collection: [ academic_unit_id => count ]
     */
    public function openCountByUnit(int $schoolYearId): Collection
    {
        return FacultyVacancy::where('school_year_id', $schoolYearId)
            ->where('status', 'open')
            ->selectRaw('academic_unit_id, COUNT(*) as count')
            ->groupBy('academic_unit_id')
            ->pluck('count', 'academic_unit_id');
    }

    /**
     * Automatically fill any open vacancy that matches the faculty member's
     * unit and position when they are first assigned a load for the term.
     * Returns the number of vacancies resolved.
     */
    public function autoFillOnAssignment(User $faculty, int $schoolYearId, int $academicTermId): int
    {
        $query = FacultyVacancy::where('school_year_id', $schoolYearId)
            ->where('status', 'open')
            ->where(function ($q) use ($academicTermId) {
                $q->where('academic_term_id', $academicTermId)
                  ->orWhereNull('academic_term_id');
            });

        if ($faculty->academic_unit_id) {
            $query->where('academic_unit_id', $faculty->academic_unit_id);
        }

        if ($faculty->sst_position_id) {
            $query->where(function ($q) use ($faculty) {
                $q->where('sst_position_id', $faculty->sst_position_id)
                  ->orWhereNull('sst_position_id');
            });
        }

        $vacancies = $query->limit(1)->get();
        $filled    = 0;

        foreach ($vacancies as $vacancy) {
            $vacancy->markFilled($faculty);
            $filled++;
        }

        return $filled;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'vacancy' => null];
    }
}
