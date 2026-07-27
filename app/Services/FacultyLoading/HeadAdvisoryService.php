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
 *   Unit Head     → AUH-{DEPT_CODE}              (e.g. AUH-CS)         → admin, 3 units
 *   Adviser G7–10 → HRA-{section_code}           (e.g. HRA-G7-NEWTON)  → admin, 3 units
 *   Adviser G11–12→ HAC-{section_code}           (e.g. HAC-G11-MARS)   → admin, 3 units
 *
 * A campus-wide Homeroom Coordinator role (covering an entire grade range,
 * not one section) is a SEPARATE, pre-existing concept — a manually-managed
 * `COORD-*` designation under the same HR_ADV/HR_ACAD category, resolved for
 * WAT via AdvisoryScheduleScopeService::gradeWideCoordinatorAssignments().
 * Do NOT reintroduce a per-section "coordinator" designation/override here —
 * one was tried (sections.homeroom_coordinator_id + HRC-* designations) and
 * reverted 2026-07-27 because it double-credited people who already held the
 * grade-wide role, inflating their admin load. See project memory.
 *
 * Section designations are auto-created from the Sections table — not hardcoded in seeders.
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
                    'load_units'              => 3,
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
            // Ensure the section's designation exists (auto-creates if needed)
            $designation = $this->ensureSectionDesignation($section);
            if (! $designation) {
                return; // Not a G7-12 section — skip
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
                'load_units'              => 3,
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
     * Ensure an HRA-{section_code} or HAC-{section_code} designation exists
     * for the given section. Only applies to grades 7–12.
     *
     * Called on section create (and as a safety fallback in syncSectionAdviser).
     * Safe to call multiple times (idempotent via code uniqueness).
     *
     * @return Designation|null  Null if section is not grade 7–12.
     */
    public function ensureSectionDesignation(Section $section): ?Designation
    {
        $grade = (int) $section->levelid;
        if ($grade < 7 || $grade > 12) {
            return null;
        }

        $isJhs = $grade <= 10;
        $prefix = $isJhs ? 'HRA' : 'HAC';

        // Derive a stable code from section_code; fall back to grade+name
        $sc   = $section->section_code ?? ('G' . $grade . '-' . strtoupper(str_replace(' ', '_', $section->sectionname)));
        $code = $prefix . '-' . $sc;

        $name = $isJhs
            ? "HR Adviser — G{$grade} {$section->sectionname}"
            : "HR/Academic Adviser — G{$grade} {$section->sectionname}";

        $categoryId = $isJhs ? $this->hraCategoryId() : $this->hacCategoryId();

        return Designation::firstOrCreate(
            ['code' => $code],
            [
                'designation_category_id' => $categoryId,
                'section_id'              => $section->id,
                'name'                    => $name,
                'load_units'              => 3,
                'assignment_type'         => 'admin',
                'requires_unit'           => false,
                'max_holders'             => 1,
                'sort_order'              => 0,
                'is_active'               => true,
            ]
        );
    }

    /**
     * Sync the section designation when a section's name or section_code changes.
     * Called on section update. Updates the designation code and/or name.
     *
     * @param Section $section   The section with its new values already saved.
     * @param string  $oldName   sectionname before the update.
     * @param string  $oldCode   section_code before the update (empty string if null).
     */
    public function syncSectionDesignation(Section $section, string $oldName, string $oldCode): void
    {
        $grade  = (int) $section->levelid;
        if ($grade < 7 || $grade > 12) {
            return;
        }

        $isJhs  = $grade <= 10;
        $prefix = $isJhs ? 'HRA' : 'HAC';

        $oldSc   = $oldCode ?: ('G' . $grade . '-' . strtoupper(str_replace(' ', '_', $oldName)));
        $oldDesigCode = $prefix . '-' . $oldSc;

        $designation = Designation::where('code', $oldDesigCode)->first();
        if (! $designation) {
            // Designation didn't exist yet — create it with the new values
            $this->ensureSectionDesignation($section);
            return;
        }

        $newSc       = $section->section_code ?? ('G' . $grade . '-' . strtoupper(str_replace(' ', '_', $section->sectionname)));
        $newDesigCode = $prefix . '-' . $newSc;
        $newName      = $isJhs
            ? "HR Adviser — G{$grade} {$section->sectionname}"
            : "HR/Academic Adviser — G{$grade} {$section->sectionname}";

        $updates = [];
        if ($newDesigCode !== $oldDesigCode) {
            $updates['code'] = $newDesigCode;
        }
        if ($section->sectionname !== $oldName) {
            $updates['name'] = $newName;
        }

        if ($updates) {
            $designation->update($updates);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

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

    private function hraCategoryId(): int
    {
        return DesignationCategory::firstOrCreate(
            ['code' => 'HR_ADV'],
            [
                'name'        => 'HR Advisory',
                'description' => 'Homeroom advisory for Grades 7–10',
                'sort_order'  => 6,
                'is_active'   => true,
            ]
        )->id;
    }

    private function hacCategoryId(): int
    {
        return DesignationCategory::firstOrCreate(
            ['code' => 'HR_ACAD'],
            [
                'name'        => 'HR/Academic Advisory',
                'description' => 'Homeroom/Academic advisory for Grades 11–12',
                'sort_order'  => 7,
                'is_active'   => true,
            ]
        )->id;
    }

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
                'full_load_threshold' => LoadComputationService::FULL_LOAD_THRESHOLD,
                'load_status'        => 'underload',
                'is_locked'          => false,
                'overload_approved'  => false,
            ]
        );
    }
}
