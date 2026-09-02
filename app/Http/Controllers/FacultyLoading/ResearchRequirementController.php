<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\AcademicTerm;
use App\Models\FacultyLoading\ResearchRequirement;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Services\FacultyLoading\RequirementFanoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ResearchRequirementController extends Controller
{
    private const PERMISSIONS = ['faculty_loading.manage', 'faculty_loading.research_advisories'];

    public function __construct(private readonly RequirementFanoutService $fanout) {}

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
            ->with(['researchGroup.advisories.faculty:id,name'])
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

    private function mapAssignment(ResearchRequirementAssignment $a): array
    {
        // Note: this deliberately does not touch $a->submissions yet — the
        // ResearchRequirementSubmission model doesn't exist until Task 15.
        // Task 16b adds the latest-submission fields once it does.
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
