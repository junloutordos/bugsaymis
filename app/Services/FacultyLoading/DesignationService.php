<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\User;

/**
 * DesignationService
 *
 * Handles the assignment of administrative/committee designations to faculty
 * as load_assignment rows and validates capacity constraints.
 *
 * Designation load flows through existing load_assignment types:
 *   - Designation category 'admin'      → assignment_type = 'admin'
 *   - Designation category 'committee'  → assignment_type = 'committee'
 *
 * The designation_id FK on load_assignments links the row to the specific
 * designation for reporting and cap enforcement.
 */
class DesignationService
{
    private LoadComputationService $loads;

    public function __construct(LoadComputationService $loads)
    {
        $this->loads = $loads;
    }

    // ── Assignment ────────────────────────────────────────────────────────────

    /**
     * Assign a designation to a faculty member for a given term.
     * Creates a load_assignment row; syncs the faculty load totals.
     *
     * @param FacultyLoad  $facultyLoad  The faculty's load record for the term.
     * @param Designation  $designation  The designation to assign.
     * @param float|null   $overrideUnits  Override the designation's default load_units.
     * @param int|null     $academicUnitId  Required when designation->requires_unit = true.
     * @param int|null     $createdBy      Assigning user's ID.
     *
     * @return array{ok: bool, message: string, assignment: ?LoadAssignment}
     */
    public function assign(
        FacultyLoad $facultyLoad,
        Designation $designation,
        ?float $overrideUnits = null,
        ?int $academicUnitId = null,
        ?int $createdBy = null
    ): array {
        // ── Validate designation is active ───────────────────────────────────
        if (! $designation->is_active) {
            return $this->fail('Designation is inactive and cannot be assigned.');
        }

        // ── Validate unit scope requirement ──────────────────────────────────
        if ($designation->requires_unit && ! $academicUnitId) {
            return $this->fail("Designation '{$designation->name}' requires an academic unit scope.");
        }

        // ── Capacity check ───────────────────────────────────────────────────
        if (! is_null($designation->max_holders)) {
            $currentCount = LoadAssignment::where('designation_id', $designation->id)
                ->where('academic_term_id', $facultyLoad->academic_term_id)
                ->count();

            if ($currentCount >= $designation->max_holders) {
                return $this->fail(
                    "Designation '{$designation->name}' has reached its maximum of "
                    . "{$designation->max_holders} concurrent holder(s) for this term."
                );
            }
        }

        // ── Prevent duplicate assignment for same faculty + term ─────────────
        $duplicate = LoadAssignment::where('faculty_load_id', $facultyLoad->id)
            ->where('designation_id', $designation->id)
            ->exists();

        if ($duplicate) {
            return $this->fail(
                "Faculty already holds the designation '{$designation->name}' for this term."
            );
        }

        // ── Determine assignment type from category code ──────────────────────
        $assignmentType = $this->resolveAssignmentType($designation);

        // ── Create the load_assignment row ───────────────────────────────────
        $assignment = LoadAssignment::create([
            'faculty_load_id'  => $facultyLoad->id,
            'user_id'          => $facultyLoad->user_id,
            'school_year_id'   => $facultyLoad->school_year_id,
            'academic_term_id' => $facultyLoad->academic_term_id,
            'assignment_type'  => $assignmentType,
            'load_units'       => $overrideUnits ?? (float) $designation->load_units,
            'description'      => $designation->name,
            'designation_id'   => $designation->id,
            'created_by'       => $createdBy,
        ]);

        // ── Sync totals ───────────────────────────────────────────────────────
        $this->loads->syncLoad($facultyLoad);

        return [
            'ok'         => true,
            'message'    => "Designation '{$designation->name}' assigned successfully.",
            'assignment' => $assignment,
        ];
    }

    /**
     * Remove a designation assignment from a faculty member's load.
     */
    public function revoke(LoadAssignment $assignment): array
    {
        if (! $assignment->designation_id) {
            return $this->fail('This load assignment is not linked to a designation.');
        }

        $facultyLoad = $assignment->facultyLoad;
        $name        = $assignment->designation->name ?? 'Unknown designation';

        $assignment->delete();
        $this->loads->syncLoad($facultyLoad);

        return ['ok' => true, 'message' => "Designation '{$name}' revoked successfully."];
    }

    // ── Queries ───────────────────────────────────────────────────────────────

    /**
     * Return all designation assignments for a faculty member in a given term.
     */
    public function getForFaculty(int $userId, int $academicTermId): \Illuminate\Support\Collection
    {
        return LoadAssignment::with('designation.category')
            ->where('user_id', $userId)
            ->where('academic_term_id', $academicTermId)
            ->whereNotNull('designation_id')
            ->get();
    }

    /**
     * Return a list of designations that still have capacity for the given term.
     */
    public function getAvailableDesignations(int $academicTermId): \Illuminate\Support\Collection
    {
        return Designation::with('category')
            ->where('is_active', true)
            ->get()
            ->filter(function (Designation $d) use ($academicTermId) {
                if (is_null($d->max_holders)) {
                    return true;
                }
                $count = LoadAssignment::where('designation_id', $d->id)
                    ->where('academic_term_id', $academicTermId)
                    ->count();
                return $count < $d->max_holders;
            })
            ->values();
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Map the designation category code to a load_assignment assignment_type.
     * Falls back to 'admin' for unrecognised categories.
     */
    private function resolveAssignmentType(Designation $designation): string
    {
        $categoryCode = strtolower($designation->category->code ?? '');

        return match (true) {
            str_contains($categoryCode, 'committee') => 'committee',
            str_contains($categoryCode, 'research')  => 'research',
            default                                   => 'admin',
        };
    }

    private function fail(string $message): array
    {
        return ['ok' => false, 'message' => $message, 'assignment' => null];
    }
}
