<?php

namespace App\Services\FacultyLoading;

use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\DesignationCategory;
use App\Models\FacultyLoading\FacultyLoad;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;

/**
 * Automatically manages LoadAssignment rows when:
 *   1. An Academic Unit Head is assigned / changed / removed
 *   2. A Section Adviser is assigned / changed / removed
 *
 * Designation mapping:
 *   Unit Head     → AUH-{DEPT_CODE}       (e.g. AUH-CS)    → admin, 6 units
 *   Adviser G7–10 → HRA-G{grade}{A–D}                      → admin, 3 units
 *   Adviser G11–12→ HAC-G{grade}{A–C}                      → admin, 3 units
 *
 * Section letter is determined by the section's insertion order (id ASC)
 * within its grade level and school year, making it stable and reproducible.
 */
class HeadAdvisoryService
{
    public function __construct(
        private LoadComputationService $loads
    ) {}

    // ── Academic Unit Head ────────────────────────────────────────────────────

    /**
     * Call after updating an academic unit's head_user_id.
     *
     * @param AcademicUnit $unit       The unit with its new head_user_id already saved.
     * @param int|null     $oldHeadId  The head_user_id before the update.
     */
    public function syncUnitHead(AcademicUnit $unit, ?int $oldHeadId): void
    {
        $newHeadId = $unit->head_user_id;

        // Nothing to do if head did not change
        if ($oldHeadId === $newHeadId) {
            return;
        }

        // ── Remove old head's AUH assignment ──────────────────────────────────
        if ($oldHeadId) {
            $this->removeUnitHeadAssignment($unit->code, $oldHeadId);
        }

        // ── Create new head's AUH assignment ──────────────────────────────────
        if ($newHeadId) {
            $designation = Designation::firstOrCreate(
                ['code' => 'AUH-' . $unit->code],
                [
                    'designation_category_id' => $this->auhCategoryId(),
                    'name'                    => 'Academic Unit Head - ' . $unit->name,
                    'load_units'              => 6,
                    'requires_unit'           => false,
                    'max_holders'             => 1,
                    'sort_order'              => 0,
                    'is_active'               => true,
                ]
            );

            $facultyLoad = $this->findOrCreateFacultyLoad($newHeadId);
            if (! $facultyLoad) {
                return; // No active school year/term — skip silently
            }

            $alreadyAssigned = LoadAssignment::where('faculty_load_id', $facultyLoad->id)
                ->where('designation_id', $designation->id)
                ->exists();

            if (! $alreadyAssigned) {
                LoadAssignment::create([
                    'faculty_load_id'  => $facultyLoad->id,
                    'user_id'          => $newHeadId,
                    'school_year_id'   => $facultyLoad->school_year_id,
                    'academic_term_id' => $facultyLoad->academic_term_id,
                    'assignment_type'  => 'admin',
                    'load_units'       => (float) $designation->load_units,
                    'description'      => $designation->name,
                    'designation_id'   => $designation->id,
                ]);

                $this->loads->syncLoad($facultyLoad);
            }
        }
    }

    // ── Section Adviser ───────────────────────────────────────────────────────

    /**
     * Call after updating a section's adviser column.
     *
     * @param Section  $section      The section with its new adviser already saved.
     * @param int|null $oldAdviserId The adviser value before the update.
     */
    public function syncSectionAdviser(Section $section, ?int $oldAdviserId): void
    {
        $newAdviserId = $section->adviser ? (int) $section->adviser : null;
        $oldAdviserId = $oldAdviserId ? (int) $oldAdviserId : null;

        // Nothing to do if adviser did not change
        if ($oldAdviserId === $newAdviserId) {
            return;
        }

        // ── Remove old adviser's homeroom assignment for this section ──────────
        if ($oldAdviserId) {
            $this->removeAdviserAssignment($section->id, $oldAdviserId);
        }

        // ── Create new adviser's homeroom assignment ───────────────────────────
        if ($newAdviserId) {
            $code = $this->getAdviserDesignationCode($section);
            if (! $code) {
                return; // Cannot determine designation slot — skip silently
            }

            $designation = Designation::where('code', $code)->first();
            if (! $designation) {
                return; // Designation not seeded — skip silently
            }

            $facultyLoad = $this->findOrCreateFacultyLoad($newAdviserId);
            if (! $facultyLoad) {
                return; // No active school year/term — skip silently
            }

            $alreadyAssigned = LoadAssignment::where('faculty_load_id', $facultyLoad->id)
                ->where('section_id', $section->id)
                ->where('designation_id', $designation->id)
                ->exists();

            if (! $alreadyAssigned) {
                LoadAssignment::create([
                    'faculty_load_id'  => $facultyLoad->id,
                    'user_id'          => $newAdviserId,
                    'school_year_id'   => $facultyLoad->school_year_id,
                    'academic_term_id' => $facultyLoad->academic_term_id,
                    'assignment_type'  => 'admin',
                    'section_id'       => $section->id,
                    'load_units'       => (float) $designation->load_units,
                    'description'      => $designation->name,
                    'designation_id'   => $designation->id,
                ]);

                $this->loads->syncLoad($facultyLoad);
            }
        }
    }

    // ── Generic designation assignment ───────────────────────────────────────

    /**
     * Assign or reassign any designation to a user.
     * Used for supervisory positions and other manually managed roles.
     *
     * @param Designation $designation  The designation to assign.
     * @param int|null    $oldUserId    Previous holder (null if new assignment).
     * @param int|null    $newUserId    New holder (null to remove assignment).
     */
    public function syncDesignationAssignment(
        Designation $designation,
        ?int $oldUserId,
        ?int $newUserId
    ): void {
        if ($oldUserId === $newUserId) {
            return;
        }

        // ── Remove old holder's assignment ────────────────────────────────────
        if ($oldUserId) {
            $assignments = LoadAssignment::where('user_id', $oldUserId)
                ->where('designation_id', $designation->id)
                ->get();

            foreach ($assignments as $assignment) {
                $facultyLoad = $assignment->facultyLoad;
                $assignment->delete();
                if ($facultyLoad) {
                    $this->loads->syncLoad($facultyLoad);
                }
            }
        }

        // ── Create new holder's assignment ────────────────────────────────────
        if ($newUserId) {
            $facultyLoad = $this->findOrCreateFacultyLoad($newUserId);
            if (! $facultyLoad) {
                return;
            }

            $alreadyAssigned = LoadAssignment::where('faculty_load_id', $facultyLoad->id)
                ->where('designation_id', $designation->id)
                ->exists();

            if (! $alreadyAssigned) {
                LoadAssignment::create([
                    'faculty_load_id'  => $facultyLoad->id,
                    'user_id'          => $newUserId,
                    'school_year_id'   => $facultyLoad->school_year_id,
                    'academic_term_id' => $facultyLoad->academic_term_id,
                    'assignment_type'  => 'admin',
                    'load_units'       => (float) $designation->load_units,
                    'description'      => $designation->name,
                    'designation_id'   => $designation->id,
                ]);

                $this->loads->syncLoad($facultyLoad);
            }
        }
    }

    // ── Designation lifecycle ────────────────────────────────────────────────

    /**
     * Ensure an AUH-{unit.code} designation exists for this unit.
     * Called on unit create. Safe to call multiple times (idempotent).
     */
    public function ensureUnitDesignation(AcademicUnit $unit): Designation
    {
        $code = 'AUH-' . $unit->code;

        return Designation::firstOrCreate(
            ['code' => $code],
            [
                'designation_category_id' => $this->auhCategoryId(),
                'name'                    => 'Academic Unit Head - ' . $unit->name,
                'load_units'              => 6,
                'requires_unit'           => false,
                'max_holders'             => 1,
                'sort_order'              => 0,
                'is_active'               => true,
            ]
        );
    }

    /**
     * Sync the AUH designation when a unit's code or name changes.
     * Renames the designation code and name to stay in sync.
     */
    public function syncUnitDesignation(AcademicUnit $unit, string $oldCode, string $oldName): void
    {
        $designation = Designation::where('code', 'AUH-' . $oldCode)->first();
        if (! $designation) {
            $this->ensureUnitDesignation($unit);
            return;
        }

        $updates = [];
        if ($unit->code !== $oldCode) {
            $updates['code'] = 'AUH-' . $unit->code;
        }
        if ($unit->name !== $oldName) {
            $updates['name'] = 'Academic Unit Head - ' . $unit->name;
        }

        if ($updates) {
            $designation->update($updates);
        }
    }

    /**
     * Get or create the AUH designation category id.
     */
    private function auhCategoryId(): int
    {
        return DesignationCategory::firstOrCreate(
            ['code' => 'AUH'],
            [
                'name'        => 'Academic Unit Head',
                'description' => 'Academic Unit Head designation for each subject department',
                'sort_order'  => 1,
                'is_active'   => true,
            ]
        )->id;
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Delete the AUH load assignment for the given unit code and user.
     * Syncs the faculty load totals after deletion.
     */
    private function removeUnitHeadAssignment(string $unitCode, int $userId): void
    {
        $designationCode = 'AUH-' . $unitCode;

        $assignments = LoadAssignment::where('user_id', $userId)
            ->whereHas('designation', fn ($q) => $q->where('code', $designationCode))
            ->get();

        foreach ($assignments as $assignment) {
            $facultyLoad = $assignment->facultyLoad;
            $assignment->delete();
            if ($facultyLoad) {
                $this->loads->syncLoad($facultyLoad);
            }
        }
    }

    /**
     * Delete the homeroom load assignment for a specific section and old adviser.
     * Syncs the faculty load totals after deletion.
     */
    private function removeAdviserAssignment(int $sectionId, int $userId): void
    {
        $hrCategoryIds = DesignationCategory::whereIn('code', ['HR_ADV', 'HR_ACAD'])
            ->pluck('id');

        $assignments = LoadAssignment::where('user_id', $userId)
            ->where('section_id', $sectionId)
            ->whereHas('designation', fn ($q) => $q->whereIn('designation_category_id', $hrCategoryIds))
            ->get();

        foreach ($assignments as $assignment) {
            $facultyLoad = $assignment->facultyLoad;
            $assignment->delete();
            if ($facultyLoad) {
                $this->loads->syncLoad($facultyLoad);
            }
        }
    }

    /**
     * Determine the designation code for a section's homeroom adviser.
     *
     * Sections are ordered by id (insertion order) within their grade + school year.
     * Position 0 → A, 1 → B, 2 → C, 3 → D
     *
     * G7–G10  → HRA-G{grade}{letter}
     * G11–G12 → HAC-G{grade}{letter}
     */
    private function getAdviserDesignationCode(Section $section): ?string
    {
        if (! $section->school_year_id) {
            return null;
        }

        $sectionIds = Section::where('school_year_id', $section->school_year_id)
            ->where('levelid', $section->levelid)
            ->orderBy('id')
            ->pluck('id')
            ->toArray();

        $position = array_search($section->id, $sectionIds, true);
        if ($position === false) {
            return null;
        }

        $letter = chr(ord('A') + $position);
        $prefix = $section->levelid <= 10 ? 'HRA' : 'HAC';

        return "{$prefix}-G{$section->levelid}{$letter}";
    }

    /**
     * Find or create the FacultyLoad record for a user in the current school year + term.
     * Returns null if no current school year or academic term is available.
     */
    private function findOrCreateFacultyLoad(int $userId): ?FacultyLoad
    {
        $sy = SchoolYear::where('is_current', true)->first();
        if (! $sy) {
            return null;
        }

        // Prefer the current term; fall back to the earliest term in the year
        $term = AcademicTerm::where('school_year_id', $sy->id)
            ->where('is_current', true)
            ->first()
            ?? AcademicTerm::where('school_year_id', $sy->id)
                ->orderBy('start_date')
                ->first();

        if (! $term) {
            return null;
        }

        return FacultyLoad::firstOrCreate(
            [
                'user_id'          => $userId,
                'school_year_id'   => $sy->id,
                'academic_term_id' => $term->id,
            ],
            [
                'teaching_units'     => 0,
                'research_units'     => 0,
                'admin_units'        => 0,
                'cocurricular_units' => 0,
                'committee_units'    => 0,
                'total_units'        => 0,
                'full_load_threshold'=> 18.0,
                'load_status'        => 'underload',
                'is_locked'          => false,
                'overload_approved'  => false,
            ]
        );
    }
}
