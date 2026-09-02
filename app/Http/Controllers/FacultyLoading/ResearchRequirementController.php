<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchGroup;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\ResearchRequirementSubmission;
use App\Services\FacultyLoading\RequirementFanoutService;
use App\Services\FacultyLoading\ResearchSubmissionFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ResearchRequirementController extends Controller
{
    private const PERMISSIONS = ['faculty_loading.manage', 'faculty_loading.research_advisories'];

    public function __construct(
        private readonly RequirementFanoutService $fanout,
        private readonly ResearchSubmissionFileService $files,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $currentTerm = AcademicTerm::where('is_current', true)->first();
        $termId      = $request->input('term_id', $currentTerm?->id);

        $requirements = ResearchRequirement::with(['createdBy:id,name', 'academicTerm.schoolYear'])
            ->when($termId, fn ($q) => $q->where('academic_term_id', $termId))
            ->orderByDesc('due_at')
            ->get()
            ->map(fn ($r) => $this->mapRequirement($r));

        $terms = AcademicTerm::with('schoolYear')->orderByDesc('start_date')->get()
            ->map(fn ($t) => ['id' => $t->id, 'label' => $t->full_label, 'is_current' => $t->is_current]);

        return Inertia::render('FacultyLoading/ResearchRequirements/Index', [
            'requirements' => $requirements,
            'terms'        => $terms,
            'currentTerm'  => $currentTerm ? ['id' => $currentTerm->id, 'label' => $currentTerm->full_label] : null,
            'filters'      => $request->only(['term_id']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $data = $request->validate([
            'academic_term_id'       => 'required|exists:academic_terms,id',
            'title'                  => 'required|string|max:255',
            'description'            => 'nullable|string|max:5000',
            'research_type'          => ['nullable', Rule::in(['thesis', 'investigatory', 'science_research', 'feasibility'])],
            'grade_levels'           => 'nullable|array',
            'grade_levels.*'         => 'integer|min:7|max:12',
            'accepted_file_types'    => 'nullable|string|max:255',
            'max_files'              => 'nullable|integer|min:1|max:20',
            'due_at'                 => 'required|date',
            'allow_late_submission'  => 'boolean',
        ]);

        $requirement = ResearchRequirement::create(array_merge($data, [
            'created_by'            => $request->user()->id,
            'max_files'             => $data['max_files'] ?? 5,
            'allow_late_submission' => $data['allow_late_submission'] ?? true,
            'status'                => 'active',
        ]));

        $created = $this->fanout->fanOut($requirement);

        return back()->with('success', "Requirement created and assigned to {$created->count()} research group(s).");
    }

    public function show(Request $request, ResearchRequirement $researchRequirement): Response
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $researchRequirement->load(['createdBy:id,name', 'academicTerm.schoolYear']);

        $assignments = $researchRequirement->assignments()
            ->with(['researchGroup.advisories.faculty:id,name', 'submissions.submittedBy:id,name', 'submissions.files'])
            ->get()
            ->map(fn ($a) => $this->mapAssignment($a));

        return Inertia::render('FacultyLoading/ResearchRequirements/Show', [
            'requirement'  => $this->mapRequirement($researchRequirement),
            'assignments'  => $assignments,
        ]);
    }

    public function update(Request $request, ResearchRequirement $researchRequirement): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $data = $request->validate([
            'title'                  => 'required|string|max:255',
            'description'            => 'nullable|string|max:5000',
            'accepted_file_types'    => 'nullable|string|max:255',
            'max_files'              => 'nullable|integer|min:1|max:20',
            'due_at'                 => 'required|date',
            'allow_late_submission'  => 'boolean',
        ]);

        $researchRequirement->update($data);

        return back()->with('success', 'Requirement updated.');
    }

    public function archive(Request $request, ResearchRequirement $researchRequirement): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $researchRequirement->update(['status' => 'archived']);

        return back()->with('success', 'Requirement archived.');
    }

    public function sync(Request $request, ResearchRequirement $researchRequirement): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $created = $this->fanout->fanOut($researchRequirement);

        return back()->with('success', "{$created->count()} new research group(s) added.");
    }

    public function addAssignment(Request $request, ResearchRequirement $researchRequirement): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $data = $request->validate(['research_group_id' => 'required|exists:research_groups,id']);

        $assignment = ResearchRequirementAssignment::firstOrNew([
            'research_requirement_id' => $researchRequirement->id,
            'research_group_id'       => $data['research_group_id'],
        ]);

        if ($assignment->exists) {
            $assignment->update(['excluded' => false]);
        } else {
            $assignment->status   = 'pending';
            $assignment->excluded = false;
            $assignment->save();
        }

        return back()->with('success', 'Research group added to requirement.');
    }

    public function toggleExcludeAssignment(Request $request, ResearchRequirementAssignment $assignment): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $assignment->update(['excluded' => ! $assignment->excluded]);

        return back()->with('success', $assignment->excluded ? 'Group excluded from requirement.' : 'Group re-included.');
    }

    public function groupsForTerm(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);

        $request->validate(['term_id' => 'required|exists:academic_terms,id']);

        $groups = ResearchGroup::where('academic_term_id', $request->term_id)
            ->active()
            ->orderBy('title')
            ->get(['id', 'title', 'grade_level', 'research_type']);

        return response()->json($groups);
    }

    public function review(Request $request, ResearchRequirementSubmission $submission): RedirectResponse
    {
        abort_unless($request->user()->hasAnyPermission(self::PERMISSIONS), 403);
        abort_if($submission->submitted_by === $request->user()->id, 403, 'You cannot review your own submission.');

        $data = $request->validate([
            'decision' => ['required', Rule::in(['accepted', 'returned'])],
            'comment'  => 'nullable|string|max:2000|required_if:decision,returned',
        ]);

        $submission->update([
            'review_status'  => $data['decision'],
            'review_comment' => $data['comment'] ?? null,
            'reviewed_by'    => $request->user()->id,
            'reviewed_at'    => now(),
        ]);

        $submission->assignment->update(['status' => $data['decision']]);

        return back()->with('success', $data['decision'] === 'accepted' ? 'Submission accepted.' : 'Submission returned for revision.');
    }

    private function mapAssignment(ResearchRequirementAssignment $a): array
    {
        $latest = $a->submissions->first();

        return [
            'id'             => $a->id,
            'status'         => $a->status,
            'excluded'       => $a->excluded,
            'research_group' => [
                'id'          => $a->researchGroup->id,
                'title'       => $a->researchGroup->title,
                'grade_level' => $a->researchGroup->grade_level,
                'advisers'    => $a->researchGroup->advisories->map(fn ($adv) => [
                    'id' => $adv->faculty->id, 'name' => $adv->faculty->name, 'role' => $adv->advisory_role,
                ])->values()->all(),
            ],
            'latest_submission' => $latest ? [
                'id'             => $latest->id,
                'notes'          => $latest->notes,
                'submitted_at'   => $latest->submitted_at->toIso8601String(),
                'is_late'        => $latest->is_late,
                'review_status'  => $latest->review_status,
                'review_comment' => $latest->review_comment,
                'submitted_by'   => $latest->submittedBy?->name,
                'files'          => $latest->files->map(fn ($f) => ['id' => $this->files->encodeKey($f->s3_key), 'name' => $f->original_filename, 'size' => $f->size_bytes])->values()->all(),
            ] : null,
        ];
    }

    private function mapRequirement(ResearchRequirement $r): array
    {
        $counts = $r->assignments()->visible()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');
        $total  = (int) $counts->sum();
        $accepted = (int) ($counts['accepted'] ?? 0);

        return [
            'id'                    => $r->id,
            'title'                 => $r->title,
            'description'           => $r->description,
            'research_type'         => $r->research_type,
            'grade_levels'          => $r->grade_levels,
            'accepted_file_types'   => $r->accepted_file_types,
            'max_files'             => $r->max_files,
            'due_at'                => $r->due_at->toIso8601String(),
            'allow_late_submission' => $r->allow_late_submission,
            'status'                => $r->status,
            'created_by'            => $r->createdBy ? ['id' => $r->createdBy->id, 'name' => $r->createdBy->name] : null,
            'term'                  => $r->academicTerm ? ['id' => $r->academicTerm->id, 'label' => $r->academicTerm->full_label] : null,
            'stats' => [
                'total'           => $total,
                'pending'         => (int) ($counts['pending'] ?? 0),
                'submitted'       => (int) ($counts['submitted'] ?? 0),
                'accepted'        => $accepted,
                'returned'        => (int) ($counts['returned'] ?? 0),
                'compliance_pct'  => $total > 0 ? (int) round(($accepted / $total) * 100) : 0,
            ],
        ];
    }
}
