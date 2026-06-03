<?php

namespace App\Http\Controllers\Registrar;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\RetentionPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RetentionPolicyController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('students.policies.manage');

        $policies = RetentionPolicy::with(['schoolYear:id,name', 'createdBy:id,name'])
            ->orderByRaw('school_year_id IS NULL DESC') // global defaults first
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($p) => [
                'id'                            => $p->id,
                'name'                          => $p->name,
                'school_year_id'                => $p->school_year_id,
                'school_year_name'              => $p->schoolYear?->name ?? 'Global Default',
                'grade_band'                    => $p->grade_band,
                'grade_band_label'              => $p->gradeBandLabel(),
                'failed_subjects_for_retention' => $p->failed_subjects_for_retention,
                'failed_subjects_for_exclusion' => $p->failed_subjects_for_exclusion,
                'min_gwa_for_promotion'         => $p->min_gwa_for_promotion,
                'honors_highest_cutoff'         => $p->honors_highest_cutoff,
                'honors_high_cutoff'            => $p->honors_high_cutoff,
                'honors_regular_cutoff'         => $p->honors_regular_cutoff,
                'is_active'                     => $p->is_active,
                'created_by_name'               => $p->createdBy?->name,
            ]);

        $schoolYears = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        return Inertia::render('Registrar/AcademicPolicies/Index', [
            'policies'    => $policies,
            'schoolYears' => $schoolYears,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('students.policies.manage');

        $data = $request->validate([
            'name'                            => ['required', 'string', 'max:150'],
            'school_year_id'                  => ['nullable', 'integer', 'exists:school_years,id'],
            'grade_band'                      => ['required', Rule::in(['jhs', 'shs', 'all'])],
            'failed_subjects_for_retention'   => ['required', 'integer', 'min:1', 'max:20'],
            'failed_subjects_for_exclusion'   => ['nullable', 'integer', 'min:1', 'max:20'],
            'min_gwa_for_promotion'           => ['nullable', 'numeric', 'min:60', 'max:100'],
            'honors_highest_cutoff'           => ['required', 'numeric', 'min:90', 'max:100'],
            'honors_high_cutoff'              => ['required', 'numeric', 'min:90', 'max:100'],
            'honors_regular_cutoff'           => ['required', 'numeric', 'min:80', 'max:100'],
            'is_active'                       => ['boolean'],
        ]);

        RetentionPolicy::create(array_merge($data, ['created_by' => Auth::id()]));

        return back()->with('success', 'Policy created.');
    }

    public function update(Request $request, RetentionPolicy $policy): RedirectResponse
    {
        $this->authorize('students.policies.manage');

        $data = $request->validate([
            'name'                            => ['required', 'string', 'max:150'],
            'school_year_id'                  => ['nullable', 'integer', 'exists:school_years,id'],
            'grade_band'                      => ['required', Rule::in(['jhs', 'shs', 'all'])],
            'failed_subjects_for_retention'   => ['required', 'integer', 'min:1', 'max:20'],
            'failed_subjects_for_exclusion'   => ['nullable', 'integer', 'min:1', 'max:20'],
            'min_gwa_for_promotion'           => ['nullable', 'numeric', 'min:60', 'max:100'],
            'honors_highest_cutoff'           => ['required', 'numeric', 'min:90', 'max:100'],
            'honors_high_cutoff'              => ['required', 'numeric', 'min:90', 'max:100'],
            'honors_regular_cutoff'           => ['required', 'numeric', 'min:80', 'max:100'],
            'is_active'                       => ['boolean'],
        ]);

        $policy->update($data);

        return back()->with('success', 'Policy updated.');
    }

    public function destroy(RetentionPolicy $policy): RedirectResponse
    {
        $this->authorize('students.policies.manage');

        $policy->delete();

        return back()->with('success', 'Policy deleted.');
    }
}
