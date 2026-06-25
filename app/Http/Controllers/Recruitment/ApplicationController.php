<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\AdvanceApplicationRequest;
use App\Http\Requests\Recruitment\StoreApplicationRequest;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\JobVacancy;
use App\Models\RecruitmentType;
use App\Services\Recruitment\ApplicationWorkflowService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApplicationController extends Controller
{
    public function __construct(private ApplicationWorkflowService $workflow) {}

    public function index(Request $request)
    {
        $this->authorize('recruitment.view');

        $query = Application::with([
            'applicant',
            'jobVacancy.jobItem.recruitmentType',
            'rankingSummary',
        ])->latest();

        if ($vacancyId = $request->input('vacancy_id')) {
            $query->where('job_vacancy_id', $vacancyId);
        }

        if ($stage = $request->input('stage')) {
            $query->where('current_stage', $stage);
        }

        if ($typeId = $request->input('type_id')) {
            $query->where('recruitment_type_id', $typeId);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('applicant', fn ($q) =>
                $q->where('last_name', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            );
        }

        $applications = $query->paginate(20)->withQueryString();

        return Inertia::render('Recruitment/Applications/Index', [
            'applications'     => $applications,
            'recruitmentTypes' => RecruitmentType::where('is_active', true)->get(['id', 'name']),
            'stages'           => Application::STAGES,
            'filters'          => $request->only(['vacancy_id', 'stage', 'type_id', 'search']),
        ]);
    }

    public function show(Application $application)
    {
        $this->authorize('recruitment.view');

        $application->load([
            'applicant.documents',
            'jobVacancy.jobItem.recruitmentType',
            'jobVacancy.jobItem.plantillaNumbers',
            'recruitmentType.evaluationCriteria',
            'evaluationScores.criteria',
            'evaluationScores.evaluator',
            'interviews',
            'rankingSummary',
            'selection.approver',
            'placement.office',
            'placement.onboardingTasks.assignee',
        ]);

        return Inertia::render('Recruitment/Applications/Show', [
            'application'         => $application,
            'availableTransitions'=> $this->workflow->availableTransitions($application),
        ]);
    }

    public function store(StoreApplicationRequest $request)
    {
        $applicant = Applicant::findOrFail($request->applicant_id);
        $vacancy   = JobVacancy::findOrFail($request->job_vacancy_id);

        try {
            $application = $this->workflow->apply($applicant, $vacancy, $request->only(['is_internal', 'remarks']));
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Application #{$application->id} submitted.");
    }

    public function advance(AdvanceApplicationRequest $request, Application $application)
    {
        try {
            $this->workflow->advance($application, $request->stage, $request->remarks);
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', "Application advanced to {$request->stage}.");
    }

    public function reject(Request $request, Application $application)
    {
        $this->authorize('recruitment.evaluate');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $this->workflow->reject($application, $validated['reason']);
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Application rejected.');
    }

    public function withdraw(Request $request, Application $application)
    {
        $this->authorize('withdraw', $application);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $this->workflow->withdraw($application, $validated['reason'] ?? '');
        } catch (\RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Application withdrawn.');
    }
}
