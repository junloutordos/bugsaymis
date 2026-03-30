<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\EvaluationCriteria;
use App\Models\OnboardingRequirement;
use App\Models\RecruitmentType;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecruitmentTypeController extends Controller
{
    public function index()
    {
        $this->authorize('recruitment.manage');

        $types = RecruitmentType::with([
            'evaluationCriteria' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
            'onboardingRequirements' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ])->get();

        return Inertia::render('Recruitment/Types/Index', [
            'types' => $types,
        ]);
    }

    public function update(Request $request, RecruitmentType $recruitmentType)
    {
        $this->authorize('recruitment.manage');

        $validated = $request->validate([
            'description'              => ['nullable', 'string', 'max:500'],
            'has_ranking'              => ['boolean'],
            'has_exam'                 => ['boolean'],
            'has_interview'            => ['boolean'],
            'requires_csc_eligibility' => ['boolean'],
            'requires_prc_license'     => ['boolean'],
            'is_active'                => ['boolean'],
        ]);

        $recruitmentType->update($validated);

        return back()->with('success', 'Recruitment type updated.');
    }

    // ── Evaluation Criteria management ─────────────────────────────────────────

    public function storeCriteria(Request $request, RecruitmentType $recruitmentType)
    {
        $this->authorize('recruitment.manage');

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'weight_percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'scoring_guide'     => ['nullable', 'string', 'max:500'],
        ]);

        $this->guardWeightTotal($recruitmentType, $validated['weight_percentage']);

        $criteria = EvaluationCriteria::create([
            ...$validated,
            'recruitment_type_id' => $recruitmentType->id,
            'is_active'           => true,
        ]);

        return back()->with('success', 'Evaluation criterion added.');
    }

    public function updateCriteria(Request $request, RecruitmentType $recruitmentType, EvaluationCriteria $criterion)
    {
        $this->authorize('recruitment.manage');

        $validated = $request->validate([
            'name'              => ['required', 'string', 'max:100'],
            'weight_percentage' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'scoring_guide'     => ['nullable', 'string', 'max:500'],
            'is_active'         => ['boolean'],
        ]);

        $this->guardWeightTotal($recruitmentType, $validated['weight_percentage'], $criterion->id);

        $criterion->update($validated);

        return back()->with('success', 'Criterion updated.');
    }

    public function destroyCriteria(RecruitmentType $recruitmentType, EvaluationCriteria $criterion)
    {
        $this->authorize('recruitment.manage');

        if ($criterion->evaluationScores()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete a criterion that has existing scores.']);
        }

        $criterion->delete();

        return back()->with('success', 'Criterion removed.');
    }

    // ── Onboarding Requirements management ─────────────────────────────────────

    public function storeOnboarding(Request $request, RecruitmentType $recruitmentType)
    {
        $this->authorize('recruitment.manage');

        $validated = $request->validate([
            'requirement_name' => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string', 'max:500'],
            'is_required'      => ['boolean'],
            'sort_order'       => ['integer', 'min:0'],
        ]);

        OnboardingRequirement::create([
            ...$validated,
            'recruitment_type_id' => $recruitmentType->id,
            'is_active'           => true,
        ]);

        return back()->with('success', 'Onboarding requirement added.');
    }

    public function destroyOnboarding(RecruitmentType $recruitmentType, OnboardingRequirement $requirement)
    {
        $this->authorize('recruitment.manage');
        $requirement->delete();
        return back()->with('success', 'Requirement removed.');
    }

    // ── Helpers ────────────────────────────────────────────────────────────────

    private function guardWeightTotal(RecruitmentType $type, float $newWeight, ?int $excludeId = null): void
    {
        $existing = EvaluationCriteria::where('recruitment_type_id', $type->id)
            ->where('is_active', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->sum('weight_percentage');

        if (($existing + $newWeight) > 100.001) {
            abort(422, "Total weight would exceed 100%. Current total: {$existing}%.");
        }
    }
}
