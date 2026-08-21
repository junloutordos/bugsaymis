<?php

namespace App\Http\Controllers\ClassRecord;

use App\Http\Controllers\Controller;
use App\Models\ClassRecord\ClassRecord;
use App\Models\ClassRecord\QuarterExamWindow;
use App\Models\ClassRecord\WatReview;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\Designation;
use App\Models\FacultyLoading\LoadAssignment;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\User;
use App\Services\ClassRecord\WatPdfPaginator;
use App\Services\ClassRecord\WatRuleService;
use App\Services\FacultyLoading\AdvisoryScheduleScopeService;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use App\Services\PersonNameFormatter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
    ) {}

    // ── GET /class-records/wat ────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user = Auth::user();
        $sy = $this->currentSchoolYear();
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

        $isAdmin = $user->hasPermission('class-records.admin');

        return Inertia::render('ClassRecord/Wat/Index', [
            'sections' => $sections->values(),
            'sectionId' => $sectionId ?: null,
            'weekStart' => $weekStart,
            'wat' => $sectionId ? WatRuleService::weekData($sectionId, $sy->id, $weekStart) : null,
            'canReview' => $canReview,
            'isCoordinator' => $isCoordinator,
            'schoolYear' => $sy->only(['id', 'name']),
            'canManageExamWindows' => $isAdmin,
            'examWindows' => $isAdmin
                ? QuarterExamWindow::where('school_year_id', $sy->id)->orderBy('quarter')->get()
                : [],
        ]);
    }

    // ── GET /class-records/wat/my-tracker ─────────────────────────────────────

    /**
     * Individual Faculty WAT Tracker — a teacher's own plotted assessments
     * across every class record they own this school year (any section,
     * any subject), for one Mon–Fri week. Open to any authenticated user
     * with at least one class record this SY (teacher_id or PEHM co-teacher);
     * unlike index()/review(), this carries no coordinator/ACIDAA gate since
     * it only ever surfaces the caller's own data.
     */
    public function myTracker(Request $request)
    {
        $user = Auth::user();
        $sy = $this->currentSchoolYear();

        $weekStart = Carbon::parse($request->query('week', now()->toDateString()))
            ->startOfWeek(Carbon::MONDAY)->toDateString();

        $hasRecords = ClassRecord::where('school_year_id', $sy->id)
            ->where('status', '<>', 'archived')
            ->where(fn ($q) => $q->where('teacher_id', $user->id)
                ->orWhereHas('coTeachers', fn ($ct) => $ct->where('user_id', $user->id)))
            ->exists();

        return Inertia::render('ClassRecord/Wat/MyTracker', [
            'weekStart' => $weekStart,
            'tracker' => $hasRecords ? WatRuleService::facultyWeekData($user->id, $sy->id, $weekStart) : null,
            'schoolYear' => $sy->only(['id', 'name']),
        ]);
    }

    // ── GET /class-records/wat/print ──────────────────────────────────────────

    /**
     * Streams the WAT as a server-rendered PDF (mPDF), replacing the earlier
     * browser-print (window.print()) approach. Chromium's interactive Print
     * Preview pipeline (what window.print() triggers) does not paginate
     * fixed-position repeating headers/footers the same way its headless
     * --print-to-pdf pipeline does — a long week's form would render as a
     * single page in Print Preview no matter how much content overflowed.
     * mPDF generates the finished, already-paginated PDF bytes server-side,
     * independent of whichever browser later opens it — same pattern already
     * used by IT Job Requests, PDS, SALN, and PPMP exports in this app.
     */
    public function printForm(Request $request): StreamedResponse
    {
        $user = Auth::user();
        $sy = $this->currentSchoolYear();
        $term = $this->currentAcademicTerm($sy);

        // Printing (and reviewing) remain Coordinator/ACIDAA/admin-only —
        // subject teachers can view the WAT but not generate or sign the
        // consolidated print form for a section they merely teach in.
        $canReview = $this->canReview($user, $sy->id);
        $isCoordinator = $this->advisoryScope->sectionIds($user, $term->id) !== [];
        abort_unless($isCoordinator || $canReview, 403);

        $sectionId = (int) $request->query('section');
        $sections = $this->accessibleSections($user, $sy->id, $term->id, $canReview, $isCoordinator);
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

        $section = $sections->firstWhere('id', $sectionId);
        $wat = WatRuleService::weekData($sectionId, $sy->id, $weekStart);

        return $this->renderPdf($section, $wat, [
            'coordinatorName' => $coordinatorName,
            'acidaaName' => $acidaaUserId ? $this->nameFormatter->formal(User::findOrFail($acidaaUserId)) : null,
            'cidChiefName' => $cidChief ? $this->nameFormatter->formal($cidChief) : null,
            'schoolYear' => $sy->only(['id', 'name']),
        ]);
    }

    /**
     * Builds and streams the WAT PDF. A4 landscape, full-bleed repeating
     * header/footer banner images sized from their own aspect ratio (avoids
     * hardcoding a margin that would silently drift if the images change).
     */
    private function renderPdf(array $section, array $wat, array $extra): StreamedResponse
    {
        $headerPath = public_path('images/report_header_landscape.jpg');
        $footerPath = public_path('images/report_footer_landscape.jpg');

        $hInfo = @getimagesize($headerPath);
        $fInfo = @getimagesize($footerPath);
        $headerMm = $hInfo ? round(($hInfo[1] / $hInfo[0]) * 297) + 3 : 28;
        $footerMm = $fInfo ? round(($fInfo[1] / $fInfo[0]) * 297) + 3 : 28;

        // Decide per-day rowspan chunk boundaries before the real render —
        // mPDF can't split a rowspan cell across a page break, so this picks
        // the split points itself (see WatPdfPaginator) instead of leaving
        // mPDF to push a whole busy day onto the next page and blank out
        // the remaining space on the current one. Margins here mirror
        // .page-body's CSS padding (1mm 10mm 0) via real mPDF page margins
        // instead — an open <div>'s CSS padding does NOT carry over to
        // child tables written in later separate WriteHTML() calls
        // (verified empirically), so native margins are the only way to
        // get the measurement pass's usable width to match the real one.
        $wat['days'] = app(WatPdfPaginator::class)->chunk($section, $wat, $extra['schoolYear'] ?? null, [
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => $headerMm + 1,
            'margin_bottom' => $footerMm,
            'tempDir' => sys_get_temp_dir(),
        ]);

        $html = view('class-record.wat-pdf', array_merge(compact('section', 'wat'), $extra))->render();

        // A multi-page WAT embeds the full-resolution header/footer JPEGs on
        // every page — raise the memory limit for this build, same as
        // IssuanceService does for its image-heavy PDFs. Not restored after:
        // PHP errors trying to shrink memory_limit below whatever's already
        // in use once mPDF has actually built the document.
        ini_set('memory_limit', '256M');

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'L',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => $headerMm,
            'margin_bottom' => $footerMm,
            'margin_header' => 0,
            'margin_footer' => 0,
            'tempDir' => sys_get_temp_dir(),
        ]);

        $mpdf->SetHTMLHeader('<img src="'.$headerPath.'" style="width:100%; display:block;">');
        $mpdf->SetHTMLFooter('<img src="'.$footerPath.'" style="width:100%; display:block;">');

        $mpdf->SetTitle("WAT — Grade {$section['level']} {$section['name']} — Week of {$wat['week_start']}");
        $mpdf->WriteHTML($html);

        $pdfBytes = $mpdf->Output('', 'S');
        $filename = 'WAT_G'.$section['level'].'-'.$section['name'].'_'.$wat['week_start'].'.pdf';

        return new StreamedResponse(function () use ($pdfBytes) {
            echo $pdfBytes;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Content-Length' => strlen($pdfBytes),
        ]);
    }

    // ── GET /class-records/wat/review ─────────────────────────────────────────

    public function review(Request $request)
    {
        $user = Auth::user();
        $sy = $this->currentSchoolYear();
        abort_unless($this->canReview($user, $sy->id), 403, 'Only the ACIDAA or a class records administrator can review the WAT.');

        $monday = Carbon::parse($request->query('week', now()->toDateString()))->startOfWeek(Carbon::MONDAY);

        $sections = $this->accessibleSections($user, $sy->id, $this->currentAcademicTerm($sy)->id, true, false);
        $reviews = WatReview::with('reviewedBy:id,name')
            ->where('school_year_id', $sy->id)
            ->where('week_start', $monday->toDateString())
            ->get()
            ->keyBy('section_id');

        $summary = $sections->map(function ($section) use ($reviews, $sy, $monday) {
            $week = WatRuleService::weekData($section['id'], $sy->id, $monday->toDateString());
            $weeklyGraded = $week['totals']['graded'];
            $weeklyMajor = $week['totals']['major'];

            return array_merge($section, [
                'graded_count' => $weeklyGraded,
                'major_count' => $weeklyMajor,
                'over_daily' => collect($week['days'])->contains('over_daily', true),
                'over_weekly' => $week['totals']['over_weekly'],
                'review' => $reviews->get($section['id']),
                // Per-teacher plotting compliance for this section/week —
                // visibility only, see WatRuleService::teacherBreakdown().
                'teacher_breakdown' => WatRuleService::teacherBreakdown(
                    $section['id'],
                    $sy->id,
                    $monday->toDateString(),
                    $week
                ),
            ]);
        });

        return Inertia::render('ClassRecord/Wat/Review', [
            'weekStart' => $monday->toDateString(),
            'summary' => $summary->values(),
            'limits' => [
                'daily_graded' => WatRuleService::DAILY_GRADED_MAX,
                'daily_major' => WatRuleService::DAILY_MAJOR_MAX,
                'weekly_graded' => WatRuleService::WEEKLY_GRADED_MAX,
                'weekly_major' => WatRuleService::WEEKLY_MAJOR_MAX,
            ],
            'schoolYear' => $sy->only(['id', 'name']),
        ]);
    }

    // ── POST /class-records/wat/review ────────────────────────────────────────

    public function storeReview(Request $request)
    {
        $user = Auth::user();
        $sy = $this->currentSchoolYear();
        abort_unless($this->canReview($user, $sy->id), 403, 'Only the ACIDAA or a class records administrator can review the WAT.');

        $validated = $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
            'week_start' => 'required|date',
            'remarks' => 'nullable|string|max:2000',
        ]);

        WatReview::updateOrCreate(
            [
                'section_id' => $validated['section_id'],
                'school_year_id' => $sy->id,
                'week_start' => Carbon::parse($validated['week_start'])->startOfWeek(Carbon::MONDAY)->toDateString(),
            ],
            [
                'status' => 'reviewed',
                'remarks' => $validated['remarks'] ?? null,
                'reviewed_by_id' => $user->id,
                'reviewed_at' => now(),
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
     *
     * Science Core/Elective synthetic sections (SCI- / ELEC- prefixed) are
     * excluded — they aren't homerooms anyone tracks on their own; their
     * assessments are pooled into their grade's real homerooms by
     * WatRuleService instead (see WatRuleService::poolSectionIds()).
     */
    private function accessibleSections(User $user, int $schoolYearId, int $academicTermId, bool $canReview, bool $isCoordinator)
    {
        $sectionIds = ClassRecord::where('school_year_id', $schoolYearId)
            ->whereNotNull('section_id')
            ->distinct()
            ->pluck('section_id');

        // A ClassRecord can end up pointing at a stray/legacy section row
        // (pre-dating the Faculty Loading school_year_id column, or never
        // properly retired) that isn't tagged to the current school year on
        // EITHER the legacy syid or the FL-native school_year_id column —
        // Faculty Loading's own Sections admin page already excludes those
        // the same way. Without this, such a row can surface here as a
        // phantom "extra" section for a grade that otherwise has only one
        // real homeroom of that name.
        $sections = Section::whereIn('id', $sectionIds)
            ->where('sectionname', 'not like', 'SCI-%')
            ->where('sectionname', 'not like', 'ELEC-%')
            ->where(fn ($q) => $q->where('syid', $schoolYearId)->orWhere('school_year_id', $schoolYearId))
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
                'id' => $s->id,
                'name' => $s->sectionname,
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
