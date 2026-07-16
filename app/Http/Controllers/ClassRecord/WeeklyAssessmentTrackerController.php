<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\ClassRecordAssessment;
use App\Models\ClassRecord\WatReview;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\User;
use App\Services\ClassRecord\WatRuleService;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * Weekly Assessment Tracker (WAT) pages.
 *
 * - Homeroom Coordinators (sections.adviser) consolidate and print their
 *   section's weekly tracker.
 * - Subject teachers can view the WAT of sections they teach.
 * - The ACIDAA (resolved through the Faculty Loading designation, same as
 *   the IPCR chain) and class-records.admin review section-weeks campus-wide.
 */
class WeeklyAssessmentTrackerController extends Controller
{
    // ── GET /class-records/wat ────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = Auth::user();
        $sy   = $this->currentSchoolYear();

        $canReview = $this->canReview($user, $sy->id);
        abort_unless($user->hasPermission('class-records.view') || $canReview, 403);

        $sections = $this->accessibleSections($user, $sy->id, $canReview);
        abort_if($sections->isEmpty() && ! $canReview, 403, 'You have no sections to track — WAT is available to section advisers and subject teachers with class records this school year.');

        $weekStart = Carbon::parse($request->query('week', now()->toDateString()))
            ->startOfWeek(Carbon::MONDAY)->toDateString();

        $sectionId = (int) $request->query('section', $sections->first()['id'] ?? 0);
        abort_unless(! $sectionId || $sections->pluck('id')->contains($sectionId), 403, 'You do not have access to that section.');

        return Inertia::render('ClassRecord/Wat/Index', [
            'sections'   => $sections->values(),
            'sectionId'  => $sectionId ?: null,
            'weekStart'  => $weekStart,
            'wat'        => $sectionId ? WatRuleService::weekData($sectionId, $sy->id, $weekStart) : null,
            'canReview'  => $canReview,
            'schoolYear' => $sy->only(['id', 'name']),
        ]);
    }

    // ── GET /class-records/wat/print ──────────────────────────────────────────

    public function printForm(Request $request)
    {
        $user = Auth::user();
        $sy   = $this->currentSchoolYear();

        $canReview = $this->canReview($user, $sy->id);
        abort_unless($user->hasPermission('class-records.view') || $canReview, 403);

        $sectionId = (int) $request->query('section');
        $sections  = $this->accessibleSections($user, $sy->id, $canReview);
        abort_unless($sections->pluck('id')->contains($sectionId), 403, 'You do not have access to that section.');

        $weekStart = Carbon::parse($request->query('week', now()->toDateString()))
            ->startOfWeek(Carbon::MONDAY)->toDateString();

        $section = Section::find($sectionId);
        $adviser = $section?->adviser ? User::find($section->adviser, ['id', 'name']) : null;

        $acidaaUserId = $this->acidaaAssignmentQuery($sy->id)->latest('id')->value('user_id');

        return Inertia::render('ClassRecord/Wat/Print', [
            'section'     => $sections->firstWhere('id', $sectionId),
            'wat'         => WatRuleService::weekData($sectionId, $sy->id, $weekStart),
            'adviserName' => $adviser?->name,
            'acidaaName'  => $acidaaUserId ? User::find($acidaaUserId, ['id', 'name'])?->name : null,
            'schoolYear'  => $sy->only(['id', 'name']),
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

        $sections = $this->accessibleSections($user, $sy->id, true);
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
     * records this SY; others see sections they advise or teach.
     */
    private function accessibleSections(User $user, int $schoolYearId, bool $canReview)
    {
        $sectionIds = ClassRecord::where('school_year_id', $schoolYearId)
            ->whereNotNull('section_id')
            ->distinct()
            ->pluck('section_id');

        $sections = Section::whereIn('id', $sectionIds)
            ->get(['id', 'sectionname', 'levelid', 'adviser']);

        if (! $canReview) {
            $taughtIds = ClassRecord::where('school_year_id', $schoolYearId)
                ->where('teacher_id', $user->id)
                ->whereNotNull('section_id')
                ->pluck('section_id');

            $sections = $sections->filter(
                fn ($s) => (int) $s->adviser === $user->id || $taughtIds->contains($s->id)
            );
        }

        return $sections
            ->sortBy([['levelid', 'asc'], ['sectionname', 'asc']])
            ->map(fn ($s) => [
                'id'         => $s->id,
                'name'       => $s->sectionname,
                'level'      => $s->levelid,
                'is_advised' => (int) $s->adviser === $user->id,
            ])
            ->values();
    }
}
