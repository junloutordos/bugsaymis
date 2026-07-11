<?php

namespace App\Services\PerformanceManagement;

use App\Models\Division;
use App\Models\EmployeeIPCR;
use App\Models\FacultyLoading\AcademicUnit;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\IPCRRatingPeriod;
use App\Models\User;
use App\Models\WorkDistributionPlan;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Central authority for the IPCR workflow (CSC MC No. 6, s. 2012 — SPMS).
 *
 * Enforces four layers of integrity:
 *  1. Terminal immutability — a Director Signed IPCR can never change.
 *  2. Period immutability   — everything in a closed rating period is read-only.
 *  3. Actor authorization   — owner / division chief / plan rater checks.
 *  4. Status state machine  — every status write goes through transition().
 */
class IPCRWorkflowService
{
    public const STATUS_NEW_TARGET       = 'New Target';
    public const STATUS_FOR_REVIEW       = 'For Review';
    public const STATUS_RETURNED         = 'Returned for Revision';
    public const STATUS_TARGETS_APPROVED = 'Targets Approved';
    public const STATUS_FOR_RATING       = 'Submitted for Rating';
    public const STATUS_RATED            = 'Rated & For PMT Review';
    public const STATUS_SUBMITTED_HR     = 'Submitted to HR';
    public const STATUS_SUBMITTED_PMT    = 'Submitted to PMT';
    public const STATUS_PMT_RETURNED     = 'PMT Returned for Revision';
    public const STATUS_PMT_APPROVED     = 'Approved by PMT';
    public const STATUS_DIRECTOR_SIGNED  = 'Director Signed';

    public const TRANSITIONS = [
        self::STATUS_NEW_TARGET       => [self::STATUS_FOR_REVIEW],
        self::STATUS_FOR_REVIEW       => [self::STATUS_TARGETS_APPROVED, self::STATUS_RETURNED],
        self::STATUS_RETURNED         => [self::STATUS_FOR_REVIEW],
        self::STATUS_TARGETS_APPROVED => [self::STATUS_FOR_RATING],
        self::STATUS_FOR_RATING       => [self::STATUS_RATED, self::STATUS_TARGETS_APPROVED],
        self::STATUS_RATED            => [self::STATUS_SUBMITTED_HR, self::STATUS_SUBMITTED_PMT, self::STATUS_TARGETS_APPROVED],
        self::STATUS_SUBMITTED_HR     => [self::STATUS_SUBMITTED_PMT],
        self::STATUS_SUBMITTED_PMT    => [self::STATUS_PMT_APPROVED, self::STATUS_PMT_RETURNED],
        self::STATUS_PMT_RETURNED     => [self::STATUS_TARGETS_APPROVED],
        self::STATUS_PMT_APPROVED     => [self::STATUS_DIRECTOR_SIGNED],
        self::STATUS_DIRECTOR_SIGNED  => [],
    ];

    private const FUNCTION_WEIGHTS = [
        'Strategic Functions' => 0.30,
        'Core Functions'      => 0.55,
        'Support Functions'   => 0.15,
    ];

    /* ----------------------------------------------------------------
     | Layers 1 + 2 — immutability
     * ---------------------------------------------------------------- */

    public function assertMutable(EmployeeIPCR $ipcr): void
    {
        abort_if(
            $ipcr->isFinalized(),
            403,
            'This IPCR has been signed by the Director and is final. It can no longer be modified.'
        );

        abort_if(
            $ipcr->isPeriodClosed(),
            403,
            'The rating period for this IPCR is closed. Records in a closed period are read-only.'
        );
    }

    /* ----------------------------------------------------------------
     | Layer 3 — actor authorization
     * ---------------------------------------------------------------- */

    /**
     * Faculty Loading designation (Supervisory) that marks the ACIDAA.
     * Prod uses code ACIDAA (id 86); dev fixtures use SUP-ACIDAA. Careful:
     * prod also has ACIDSA (Student Affairs) — never match on a broad
     * "Assistant CID Chief%" prefix.
     */
    public const ACIDAA_DESIGNATION_CODES = ['ACIDAA', 'SUP-ACIDAA'];

    /** @var array<string, mixed> per-request memo for chain lookups */
    private array $memo = [];

    public function assertOwner(User $user, EmployeeIPCR $ipcr): void
    {
        abort_if($ipcr->user_id !== $user->id, 403, 'You can only modify your own IPCR.');
    }

    /**
     * SPMS immediate supervisor — the person who approves targets, rates and
     * marks the IPCR as Rated:
     *   ACIDAA            → CID Chief
     *   Academic Unit Head→ ACIDAA
     *   Faculty in a unit → their Academic Unit Head
     *   Everyone else     → Division Chief
     * Every academic lookup falls back to the Division Chief, so divisions
     * without the CID structures behave exactly as before.
     */
    public function immediateSupervisorFor(User $employee): ?User
    {
        // A Division Chief's own IPCR is managed by the Campus Director (OCD)
        // — without this, the resolver would self-exclude and return null.
        if (Division::where('division_chief_id', $employee->id)->exists()) {
            return $this->firstNotSelf($employee, $this->ocdUser());
        }

        if ($this->holdsAcidaaDesignation($employee)) {
            return $this->firstNotSelf($employee, $this->cidChief(), $this->divisionChiefOf($employee));
        }

        if ($this->currentAcademicUnits()->where('head_user_id', $employee->id)->isNotEmpty()) {
            return $this->firstNotSelf($employee, $this->acidaaHolder(), $this->cidChief(), $this->divisionChiefOf($employee));
        }

        $head = $this->academicUnitHeadFor($employee);
        if ($head) {
            return $this->firstNotSelf($employee, $head, $this->divisionChiefOf($employee));
        }

        return $this->firstNotSelf($employee, $this->divisionChiefOf($employee));
    }

    /**
     * The AUH supervising a teacher. A faculty member's academic unit is
     * their office/unit in Data Management (users.office_id) — Faculty
     * Loading's "Sync to Offices" keeps offices.unit_head aligned with the
     * unit's head. The office link only counts when the office is in the
     * CID division AND its unit_head currently heads an active current-SY
     * academic unit (rejects stale unit heads and CID lab offices).
     * Explicit users.academic_unit_id wins; current-SY teaching-subject
     * derivation remains as a last resort.
     */
    private function academicUnitHeadFor(User $employee): ?User
    {
        $units = $this->currentAcademicUnits();

        // 1. Explicit unit assignment
        if ($employee->academic_unit_id) {
            $headId = $units->firstWhere('id', (int) $employee->academic_unit_id)?->head_user_id;
            if ($headId) {
                return User::find($headId);
            }
        }

        // 2. Data Management office link
        if ($employee->office_id && ($cidDivisionId = $this->cidDivisionId())) {
            $unitHead = DB::table('offices')
                ->where('id', $employee->office_id)
                ->where('division_id', $cidDivisionId)
                ->value('unit_head');

            if ($unitHead && $units->contains('head_user_id', (int) $unitHead)) {
                return User::find($unitHead);
            }
        }

        // 3. Derive from the unit whose subjects dominate their teaching load
        $currentSyId = $this->currentSchoolYearId();
        if (! $currentSyId) {
            return null;
        }

        $unitId = DB::table('load_assignments')
            ->join('subjects', 'subjects.id', '=', 'load_assignments.subject_id')
            ->where('load_assignments.user_id', $employee->id)
            ->where('load_assignments.school_year_id', $currentSyId)
            ->whereNotNull('subjects.academic_unit_id')
            ->selectRaw('subjects.academic_unit_id, COUNT(*) as n')
            ->groupBy('subjects.academic_unit_id')
            ->orderByDesc('n')
            ->value('academic_unit_id');

        $headId = $unitId ? $units->firstWhere('id', (int) $unitId)?->head_user_id : null;

        return $headId ? User::find($headId) : null;
    }

    private function currentSchoolYearId(): ?int
    {
        if (! array_key_exists('currentSyId', $this->memo)) {
            $this->memo['currentSyId'] = SchoolYear::where('is_current', true)->value('id');
        }

        return $this->memo['currentSyId'];
    }

    /** Active academic units of the current SY (memoized collection). */
    private function currentAcademicUnits()
    {
        if (! array_key_exists('currentUnits', $this->memo)) {
            $syId = $this->currentSchoolYearId();
            $this->memo['currentUnits'] = $syId
                ? AcademicUnit::active()->where('school_year_id', $syId)->get(['id', 'head_user_id'])
                : collect();
        }

        return $this->memo['currentUnits'];
    }

    private function cidDivisionId(): ?int
    {
        if (! array_key_exists('cidDivisionId', $this->memo)) {
            $this->memo['cidDivisionId'] = Division::where('acronym', 'CID')->value('id');
        }

        return $this->memo['cidDivisionId'];
    }

    private function firstNotSelf(User $employee, ?User ...$candidates): ?User
    {
        foreach ($candidates as $candidate) {
            if ($candidate && $candidate->id !== $employee->id) {
                return $candidate;
            }
        }

        return null;
    }

    private function divisionChiefOf(User $employee): ?User
    {
        $chiefId = Division::where('id', $employee->division_id)->value('division_chief_id');

        return $chiefId ? User::find($chiefId) : null;
    }

    private function cidChief(): ?User
    {
        return $this->memo['cidChief'] ??= User::havingRole('CID Chief')
            ->where('status', '<>', 'inactive')
            ->first();
    }

    private function ocdUser(): ?User
    {
        return $this->memo['ocd'] ??= User::havingRole('OCD')
            ->where('status', '<>', 'inactive')
            ->first();
    }

    private function acidaaDesignationIds()
    {
        return Designation::whereIn('code', self::ACIDAA_DESIGNATION_CODES)
            ->orWhere('name', 'like', 'Assistant CID Chief for Academic Affairs%')
            ->pluck('id');
    }

    private function acidaaHolder(): ?User
    {
        if (array_key_exists('acidaa', $this->memo)) {
            return $this->memo['acidaa'];
        }

        $userId = $this->acidaaAssignmentQuery()->latest('id')->value('user_id');

        return $this->memo['acidaa'] = ($userId ? User::find($userId) : null);
    }

    private function holdsAcidaaDesignation(User $user): bool
    {
        return $this->acidaaAssignmentQuery()->where('user_id', $user->id)->exists();
    }

    private function acidaaAssignmentQuery()
    {
        $designationIds = $this->acidaaDesignationIds();
        $currentSyId    = $this->currentSchoolYearId();

        return LoadAssignment::whereIn('designation_id', $designationIds)
            ->when($currentSyId, fn ($q) => $q->where('school_year_id', $currentSyId));
    }

    /**
     * IDs of employees whose immediate supervisor is $user through the
     * academic chain (used for list visibility; per-IPCR actions are still
     * verified by canManage). Division-chief supervision is handled by the
     * existing division branch, not here.
     */
    public function supervisedUserIds(User $user): \Illuminate\Support\Collection
    {
        $ids = collect();

        // Teachers in academic units the user heads (current SY) — via their
        // Data Management office (offices.unit_head = me, CID division),
        // explicit assignment, or teaching that unit's subjects this SY
        // (mirrors academicUnitHeadFor)
        $unitIds = $this->currentAcademicUnits()->where('head_user_id', $user->id)->pluck('id');
        if ($unitIds->isNotEmpty()) {
            $ids = $ids->merge(User::whereIn('academic_unit_id', $unitIds)->pluck('id'));

            if ($cidDivisionId = $this->cidDivisionId()) {
                $officeIds = DB::table('offices')
                    ->where('division_id', $cidDivisionId)
                    ->where('unit_head', $user->id)
                    ->pluck('id');
                if ($officeIds->isNotEmpty()) {
                    $ids = $ids->merge(User::whereIn('office_id', $officeIds)->pluck('id'));
                }
            }

            if ($currentSyId = $this->currentSchoolYearId()) {
                $ids = $ids->merge(
                    DB::table('load_assignments')
                        ->join('subjects', 'subjects.id', '=', 'load_assignments.subject_id')
                        ->whereIn('subjects.academic_unit_id', $unitIds)
                        ->where('load_assignments.school_year_id', $currentSyId)
                        ->distinct()
                        ->pluck('load_assignments.user_id')
                );
            }
        }

        // Academic Unit Heads (current SY), if the user is the ACIDAA
        if ($this->holdsAcidaaDesignation($user)) {
            $ids = $ids->merge($this->currentAcademicUnits()->whereNotNull('head_user_id')->pluck('head_user_id'));
        }

        // The ACIDAA, if the user is the CID Chief
        if ($user->hasRole('CID Chief')) {
            $acidaa = $this->acidaaHolder();
            if ($acidaa) {
                $ids->push($acidaa->id);
            }
        }

        return $ids->unique()->reject(fn ($id) => $id == $user->id)->values();
    }

    public function canManage(User $user, EmployeeIPCR $ipcr): bool
    {
        $ipcr->loadMissing('user');
        if (! $ipcr->user) {
            return $user->hasRole('OCD');
        }

        $supervisor = $this->immediateSupervisorFor($ipcr->user);

        return $user->hasRole('OCD') || ($supervisor && $supervisor->id === $user->id);
    }

    public function assertCanManage(User $user, EmployeeIPCR $ipcr): void
    {
        $ipcr->loadMissing('user');

        abort_unless(
            $this->canManage($user, $ipcr),
            403,
            "You are not this employee's immediate supervisor and cannot act on this IPCR."
        );
    }

    /**
     * Ministerial actions (submit to PMT / HR, relay PMT returns) belong to
     * the Division Chief — for CID faculty this is the CID Chief, who does
     * not rate or approve targets but endorses the finished IPCR.
     */
    public function canEndorse(User $user, EmployeeIPCR $ipcr): bool
    {
        $employeeDivisionChiefId = Division::where('id', $ipcr->user?->division_id)->value('division_chief_id');

        return $user->hasRole('OCD') || ($employeeDivisionChiefId && $user->id == $employeeDivisionChiefId);
    }

    public function assertCanEndorse(User $user, EmployeeIPCR $ipcr): void
    {
        $ipcr->loadMissing('user');

        abort_unless(
            $this->canEndorse($user, $ipcr),
            403,
            "You are not this employee's Division Chief and cannot endorse this IPCR."
        );
    }

    public function canRatePlan(User $user, EmployeeIPCR $ipcr, WorkDistributionPlan $plan): bool
    {
        $plan->loadMissing(['offices', 'committees', 'specialAssignments']);
        $ipcr->loadMissing('user');

        return $this->canManage($user, $ipcr) || match ($plan->rated_by) {
            'Unit Head'          => $plan->offices->contains(fn ($o) => $o->unit_head == $user->id),
            'Committee Head'     => $plan->committees->contains(fn ($c) => $c->head_id == $user->id),
            'Coordinator'        => $plan->specialAssignments->contains(fn ($a) => $a->coordinator_id == $user->id),
            'Academic Unit Head' => $this->immediateSupervisorFor($ipcr->user)?->id === $user->id,
            default              => false,
        };
    }

    /* ----------------------------------------------------------------
     | Layer 4 — state machine
     * ---------------------------------------------------------------- */

    /**
     * Move an IPCR to a new status. Validates immutability and the
     * transition map, applies $extra attributes atomically, logs.
     */
    public function transition(EmployeeIPCR $ipcr, string $to, array $extra = [], ?string $auditAction = null): EmployeeIPCR
    {
        $this->assertMutable($ipcr);

        return DB::transaction(function () use ($ipcr, $to, $extra, $auditAction) {
            $fresh = EmployeeIPCR::whereKey($ipcr->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$fresh->status] ?? [];
            abort_unless(
                in_array($to, $allowed, true),
                403,
                "Invalid IPCR status change: \"{$fresh->status}\" cannot move to \"{$to}\"."
            );

            $fresh->update(array_merge($extra, ['status' => $to]));

            AuditLogger::log([
                'action'         => $auditAction ?? 'ipcr_status_changed',
                'auditable_type' => EmployeeIPCR::class,
                'auditable_id'   => $fresh->id,
                'new_values'     => array_merge(['status' => $to], $extra),
            ]);

            $ipcr->refresh();

            return $ipcr;
        });
    }

    /* ----------------------------------------------------------------
     | Creation guards
     * ---------------------------------------------------------------- */

    public function assertPeriodAcceptsNewTargets(IPCRRatingPeriod $period): void
    {
        if (! $period->isOpen()) {
            throw ValidationException::withMessages([
                'rating_period_id' => "The rating period \"{$period->label}\" is closed and no longer accepts IPCRs.",
            ]);
        }
    }

    public function assertNoDuplicateForPeriod(int $userId, int $periodId, ?int $ignoreIpcrId = null): void
    {
        $exists = EmployeeIPCR::where('user_id', $userId)
            ->where('rating_period_id', $periodId)
            ->when($ignoreIpcrId, fn ($q) => $q->where('id', '!=', $ignoreIpcrId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'rating_period_id' => 'You already have an IPCR for this rating period. The SPMS allows one IPCR per employee per semestral period.',
            ]);
        }
    }

    /* ----------------------------------------------------------------
     | Ratings (CSC SPMS: 5-point scale, strategic/core/support weights)
     * ---------------------------------------------------------------- */

    public function normalizeFunctionType(?string $raw): string
    {
        if (! $raw) return 'Uncategorized';
        $t = strtolower(trim($raw));
        if (in_array($t, ['strategic', 'strategic functions', 'strategic function'])) return 'Strategic Functions';
        if (in_array($t, ['core', 'core functions', 'core function']))               return 'Core Functions';
        if (in_array($t, ['support', 'support functions', 'support function']))      return 'Support Functions';

        return trim($raw);
    }

    /**
     * Weighted final rating (Strategic 30%, Core 55%, Support 15%).
     * Mirrors DivisionChiefIPCRShow.vue / PMTIPCRShow.vue.
     */
    public function computeWeightedAverage($plans): ?float
    {
        $groups = [];

        foreach ($plans as $plan) {
            $ft     = $this->normalizeFunctionType($plan->performance_indicator?->agencyOutcome?->function_type);
            $supAvg = $plan->pivot?->sup_average;
            if ($supAvg === null || $supAvg === '') continue;
            $groups[$ft]['total'] = ($groups[$ft]['total'] ?? 0) + (float) $supAvg;
            $groups[$ft]['count'] = ($groups[$ft]['count'] ?? 0) + 1;
        }

        $totalWeighted = 0;
        $hasAny        = false;
        foreach ($groups as $ft => $data) {
            $weight         = self::FUNCTION_WEIGHTS[$ft] ?? 0;
            $totalWeighted += ($data['total'] / $data['count']) * $weight;
            $hasAny         = true;
        }

        return $hasAny ? round($totalWeighted, 2) : null;
    }

    /**
     * CSC SPMS adjectival bands on the 5-point scale.
     * Single server-side switch point for the rating scale.
     */
    public function adjectivalRating(?float $value): ?string
    {
        if ($value === null) return null;

        return match (true) {
            $value >= 4.51 => 'Outstanding',
            $value >= 3.51 => 'Very Satisfactory',
            $value >= 2.51 => 'Satisfactory',
            $value >= 1.51 => 'Unsatisfactory',
            default        => 'Poor',
        };
    }

    /* ----------------------------------------------------------------
     | Finalization
     * ---------------------------------------------------------------- */

    /**
     * Director signs the IPCR: terminal transition + immutable snapshot
     * of the final numeric and adjectival rating.
     */
    public function finalize(EmployeeIPCR $ipcr, User $director): EmployeeIPCR
    {
        $ipcr->loadMissing('plans.performance_indicator.agencyOutcome');
        $finalNumeric = $this->computeWeightedAverage($ipcr->plans);

        return $this->transition($ipcr, self::STATUS_DIRECTOR_SIGNED, [
            'director_signed_at'      => now(),
            'director_signature'      => $director->electronic_signature,
            'final_numeric_rating'    => $finalNumeric,
            'final_adjectival_rating' => $this->adjectivalRating($finalNumeric),
        ], 'ipcr_director_signed');
    }
}
