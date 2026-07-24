<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\WatReview;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\User;
use App\Services\ClassRecord\WatRuleService;
use App\Services\FacultyLoading\AdvisoryScheduleScopeService;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use App\Services\PersonNameFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Weekly Assessment Tracker (WAT) pages.
 *
 * - Homeroom Coordinators consolidate and print the WAT for their assigned
 *   section(s)/grade range. Coordinator scope is resolved through
 *   AdvisoryScheduleScopeService from the Designations module (HR_ADV /
 *   HR_ACAD categories) — NOT the legacy Section::adviser column, and NOT
 *   tied to holding a teaching load.
 * - Subject teachers may VIEW the WAT (read-only) for sections where they
 *   hold a 'teaching' LoadAssignment, but may NOT print the form or review
 *   other sections — those actions remain Coordinator/ACIDAA/admin-only.
 * - The ACIDAA (resolved through the Faculty Loading designation, same as
 *   the IPCR chain) and class-records.admin review section-weeks campus-wide.
 */
class WeeklyAssessmentTrackerController extends Controller
{
    public function __construct(
        private AdvisoryScheduleScopeService $advisoryScope,
        private PersonNameFormatter $nameFormatter,
    ) {
    }

    // ── GET /class-records/wat ────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = Auth::user();
        $sy   = $this->currentSchoolYear();
        $term = $this->currentAcademicTerm($sy);

        $canReview = $this->canReview($user, $sy->id);
        $isCoordinator = $this->advisoryScope->sectionIds($user, $term->id) !== [];
        $isTeacher = $this->teachingSectionIds($user, $sy->id) !== [];
        abort_unless($isCoordinator || $canReview || $isTeacher, 403);

        $sections = $this->accessibleSections($user, $sy->id, $term->id, $canReview, $isCoordinator);
        abort_if($sections->isEmpty(), 403, 'You have no sections to track — WAT is available to Homeroom Coordinators (via Designations) and to subject teachers for the sections they teach.');

        $weekStart = Carbon::parse($request->query('week', now()->toDateString()))
            ->startOfWeek(Carbon::MONDAY)->toDateString();

        $sectionId = (int) $request->query('section', $sections->first()['id'] ?? 0);
        abort_unless(! $sectionId || $sections->pluck('id')->contains($sectionId), 403, 'You do not have access to that section.');

        return Inertia::render('ClassRecord/Wat/Index', [
            'sections'      => $sections->values(),
            'sectionId'     => $sectionId ?: null,
            'weekStart'     => $weekStart,
            'wat'           => $sectionId ? WatRuleService::weekData($sectionId, $sy->id, $weekStart) : null,
            'canReview'     => $canReview,
            'isCoordinator' => $isCoordinator,
            'schoolYear'    => $sy->only(['id', 'name']),
        ]);
    }

    // ── GET /class-records/wat/print ──────────────────────────────────────────

    public function printForm(Request $request)
    {
        $user = Auth::user();
        $sy   = $this->currentSchoolYear();
        $term = $this->currentAcademicTerm($sy);

        // Printing (and reviewing) remain Coordinator/ACIDAA/admin-only —
        // subject teachers can view the WAT but not generate or sign the
        // consolidated print form for a section they merely teach in.
        $canReview = $this->canReview($user, $sy->id);
        $isCoordinator = $this->advisoryScope->sectionIds($user, $term->id) !== [];
        abort_unless($isCoordinator || $canReview, 403);

        $sectionId = (int) $request->query('section');
        $sections  = $this->accessibleSections($user, $sy->id, $term->id, $canReview, $isCoordinator);
        abort_unless($sections->pluck('id')->contains($sectionId), 403, 'You do not have access to that section.');

        $weekStart = Carbon::parse($request->query('week', now()->toDateString()))
            ->startOfWeek(Carbon::MONDAY)->toDateString();

        $coordinatorUserId = $this->advisoryScope
            ->adviserUserIdsBySection($term->id, [$sectionId])
            ->get($sectionId);
        $coordinatorName = $coordinatorUserId
            ? $this->nameFormatter->formal(User::findOrFail($coordinatorUserId))
            : null;

        $acidaaUserId = $this->acidaaAssignmentQuery($sy->id)->latest('id')->value('user_id');

        $cidChief = User::whereHas('roles', fn ($q) => $q->where('name', 'CID Chief'))
            ->where('status', '<>', 'inactive')
            ->first();

        return Inertia::render('ClassRecord/Wat/Print', [
            'section'         => $sections->firstWhere('id', $sectionId),
            'wat'             => WatRuleService::weekData($sectionId, $sy->id, $weekStart),
            'coordinatorName' => $coordinatorName,
            'acidaaName'      => $acidaaUserId ? $this->nameFormatter->formal(User::findOrFail($acidaaUserId)) : null,
            'cidChiefName'    => $cidChief ? $this->nameFormatter->formal($cidChief) : null,
            'schoolYear'      => $sy->only(['id', 'name']),
        ]);
    }

    // ── GET /class-records/wat/review ─────────────────────────────────────────

    public function review(Request $request)
    {
        $user = Auth::user();
        $sy   = $this->currentSchoolYear();
        abort_unless($this->canReview($user, $sy->id), 403, 'Only the ACIDAA or a class records administrator can review the WAT.');

        $monday = Carbon::parse($request->query('week', now()->toDateString()))->startOfWeek(Carbon::MONDAY);

        // Per-section, per-day graded/major tallies for the week
        $rows = ClassRecordAssessment::schoolYearScopeQuery($sy->id)
            ->whereNotNull('cr.section_id')
            ->whereBetween('class_record_assessments.activity_date', [
                $monday->toDateString(),
                $monday->copy()->addDays(6)->toDateString(),
            ])
            ->selectRaw('
                cr.section_id,
                class_record_assessments.activity_date as d,
                COALESCE(SUM(class_record_assessments.is_graded), 0) as graded,
                COALESCE(SUM(class_record_assessments.is_major AND class_record_assessments.is_graded), 0) as major
            ')
            ->groupBy('cr.section_id', 'd')
            ->get();

        $sections = $this->accessibleSections($user, $sy->id, $this->currentAcademicTerm($sy)->id, true, false);
        $reviews  = WatReview::with('reviewedBy:id,name')
            ->where('school_year_id', $sy->id)
            ->where('week_start', $monday->toDateString())
            ->get()
            ->keyBy('section_id');

        $summary = $sections->map(function ($section) use ($rows, $reviews) {
            $days = $rows->where('section_id', $section['id']);

            return array_merge($section, [
                'graded_count' => (int) $days->sum('graded'),
                'major_count'  => (int) $days->sum('major'),
                'over_daily'   => $days->contains(fn ($d) => $d->graded > WatRuleService::DAILY_GRADED_MAX || $d->major > WatRuleService::DAILY_MAJOR_MAX),
                'over_weekly'  => $days->sum('graded') > WatRuleService::WEEKLY_GRADED_MAX || $days->sum('major') > WatRuleService::WEEKLY_MAJOR_MAX,
                'review'       => $reviews->get($section['id']),
            ]);
        });

        return Inertia::render('ClassRecord/Wat/Review', [
            'weekStart'  => $monday->toDateString(),
            'summary'    => $summary->values(),
            'limits'     => [
                'daily_graded'  => WatRuleService::DAILY_GRADED_MAX,
                'daily_major'   => WatRuleService::DAILY_MAJOR_MAX,
                'weekly_graded' => WatRuleService::WEEKLY_GRADED_MAX,
                'weekly_major'  => WatRuleService::WEEKLY_MAJOR_MAX,
            ],
            'schoolYear' => $sy->only(['id', 'name']),
        ]);
    }

    // ── POST /class-records/wat/review ────────────────────────────────────────

    public function storeReview(Request $request)
    {
        $user = Auth::user();
        $sy   = $this->currentSchoolYear();
        abort_unless($this->canReview($user, $sy->id), 403, 'Only the ACIDAA or a class records administrator can review the WAT.');

        $validated = $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
            'week_start' => 'required|date',
            'remarks'    => 'nullable|string|max:2000',
        ]);

        WatReview::updateOrCreate(
            [
                'section_id'     => $validated['section_id'],
                'school_year_id' => $sy->id,
                'week_start'     => Carbon::parse($validated['week_start'])->startOfWeek(Carbon::MONDAY)->toDateString(),
            ],
            [
                'status'         => 'reviewed',
                'remarks'        => $validated['remarks'] ?? null,
                'reviewed_by_id' => $user->id,
                'reviewed_at'    => now(),
            ]
        );

        return back()->with('success', 'Weekly Assessment Tracker marked as reviewed.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function currentSchoolYear(): SchoolYear
    {
        $sy = SchoolYear::where('is_current', true)->first();
        abort_if(! $sy, 422, 'No current school year is set.');

        return $sy;
    }

    private function currentAcademicTerm(SchoolYear $sy): AcademicTerm
    {
        $term = AcademicTerm::where('school_year_id', $sy->id)->where('is_current', true)->first();
        abort_if(! $term, 422, 'No current academic term is set.');

        return $term;
    }

    private function canReview(User $user, int $schoolYearId): bool
    {
        return $user->hasPermission('class-records.admin')
            || $this->acidaaAssignmentQuery($schoolYearId)->where('user_id', $user->id)->exists();
    }

    private function acidaaAssignmentQuery(int $schoolYearId)
    {
        $designationIds = Designation::whereIn('code', IPCRWorkflowService::ACIDAA_DESIGNATION_CODES)
            ->orWhere('name', 'like', 'Assistant CID Chief for Academic Affairs%')
            ->pluck('id');

        return LoadAssignment::whereIn('designation_id', $designationIds)
            ->where('school_year_id', $schoolYearId);
    }

    /**
     * Sections the user may track: reviewers see every section with class
     * records this SY; Homeroom Coordinators see only their designated
     * section(s)/grade range, resolved via AdvisoryScheduleScopeService from
     * the Designations module (HR_ADV / HR_ACAD categories) — NOT the legacy
     * Section::adviser column, and NOT a function of holding a teaching load.
     * Subject teachers (no coordinator designation) see only the sections
     * where they hold a 'teaching' LoadAssignment — read-only, resolved via
     * teachingSectionIds().
     */
    private function accessibleSections(User $user, int $schoolYearId, int $academicTermId, bool $canReview, bool $isCoordinator)
    {
        $sectionIds = ClassRecord::where('school_year_id', $schoolYearId)
            ->whereNotNull('section_id')
            ->distinct()
            ->pluck('section_id');

        $sections = Section::whereIn('id', $sectionIds)
            ->get(['id', 'sectionname', 'levelid']);

        if (! $canReview) {
            $scopedSectionIds = $isCoordinator
                ? $this->advisoryScope->sectionIds($user, $academicTermId)
                : $this->teachingSectionIds($user, $schoolYearId);
            $sections = $sections->filter(fn ($s) => in_array((int) $s->id, $scopedSectionIds, true));
        }

        return $sections
            ->sortBy([['levelid', 'asc'], ['sectionname', 'asc']])
            ->map(fn ($s) => [
                'id'    => $s->id,
                'name'  => $s->sectionname,
                'level' => $s->levelid,
            ])
            ->values();
    }

    /**
     * Sections where the user holds a 'teaching' LoadAssignment this school
     * year — the WAT scope for plain subject teachers (view-only; does not
     * grant print or review access).
     *
     * @return array<int>
     */
    private function teachingSectionIds(User $user, int $schoolYearId): array
    {
        return LoadAssignment::where('user_id', $user->id)
            ->where('school_year_id', $schoolYearId)
            ->where('assignment_type', 'teaching')
            ->whereNotNull('section_id')
            ->pluck('section_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
