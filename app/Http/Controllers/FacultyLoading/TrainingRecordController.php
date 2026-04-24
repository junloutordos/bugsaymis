<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\TrainingRecord;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrainingRecordController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.training');

        $schoolYears = SchoolYear::orderByDesc('start_date')->get(['id', 'name', 'is_current']);

        $selectedYear = $request->input('school_year_id',
            $schoolYears->firstWhere('is_current', true)?->id ?? $schoolYears->first()?->id
        );

        $query = TrainingRecord::with(['user', 'verifier'])
            ->when($selectedYear, fn ($q) => $q->where('school_year_id', $selectedYear))
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('level'), fn ($q) => $q->where('level', $request->level));

        $records = $query->orderByDesc('date_from')->get()->map(fn ($r) => [
            'id'             => $r->id,
            'user'           => ['id' => $r->user?->id, 'name' => $r->user?->name],
            'training_title' => $r->training_title,
            'organizer'      => $r->organizer,
            'venue'          => $r->venue,
            'date_from'      => $r->date_from?->toDateString(),
            'date_to'        => $r->date_to?->toDateString(),
            'level'          => $r->level,
            'hours_earned'   => $r->hours_earned,
            'load_units'     => $r->load_units,
            'status'         => $r->status,
            'verified_by'    => $r->verifier?->name,
            'verified_at'    => $r->verified_at?->toDateString(),
            'remarks'        => $r->remarks,
        ]);

        return Inertia::render('FacultyLoading/TrainingRecords/Index', [
            'records'        => $records,
            'schoolYears'    => $schoolYears,
            'selectedYearId' => (int) $selectedYear,
            'filters'        => $request->only(['user_id', 'status', 'level']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('faculty_loading.training');

        $data = $request->validate([
            'user_id'        => 'required|exists:users,id',
            'school_year_id' => 'nullable|exists:school_years,id',
            'training_title' => 'required|string|max:255',
            'organizer'      => 'nullable|string|max:200',
            'venue'          => 'nullable|string|max:200',
            'date_from'      => 'required|date',
            'date_to'        => 'required|date|after_or_equal:date_from',
            'level'          => 'required|in:international,national,regional,division,school',
            'hours_earned'   => 'required|numeric|min:0',
            'load_units'     => 'nullable|numeric|min:0',
            'remarks'        => 'nullable|string',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status']     = 'pending';

        TrainingRecord::create($data);

        return back()->with('success', 'Training record created.');
    }

    public function update(Request $request, TrainingRecord $trainingRecord): RedirectResponse
    {
        $this->authorize('faculty_loading.training');

        if ($trainingRecord->status === 'verified') {
            return back()->withErrors(['error' => 'Cannot edit a verified training record.']);
        }

        $data = $request->validate([
            'training_title' => 'required|string|max:255',
            'organizer'      => 'nullable|string|max:200',
            'venue'          => 'nullable|string|max:200',
            'date_from'      => 'required|date',
            'date_to'        => 'required|date|after_or_equal:date_from',
            'level'          => 'required|in:international,national,regional,division,school',
            'hours_earned'   => 'required|numeric|min:0',
            'load_units'     => 'nullable|numeric|min:0',
            'remarks'        => 'nullable|string',
        ]);

        $trainingRecord->update($data);

        return back()->with('success', 'Training record updated.');
    }

    public function verify(Request $request, TrainingRecord $trainingRecord): RedirectResponse
    {
        $this->authorize('faculty_loading.training.verify');

        if ($trainingRecord->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending records can be verified.']);
        }

        $trainingRecord->update([
            'status'      => 'verified',
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return back()->with('success', 'Training record verified.');
    }

    public function reject(Request $request, TrainingRecord $trainingRecord): RedirectResponse
    {
        $this->authorize('faculty_loading.training.verify');

        if ($trainingRecord->status !== 'pending') {
            return back()->withErrors(['error' => 'Only pending records can be rejected.']);
        }

        $data = $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $trainingRecord->update([
            'status'  => 'rejected',
            'remarks' => $data['remarks'],
        ]);

        return back()->with('success', 'Training record rejected.');
    }

    public function destroy(TrainingRecord $trainingRecord): RedirectResponse
    {
        $this->authorize('faculty_loading.training');

        if ($trainingRecord->status === 'verified') {
            return back()->withErrors(['error' => 'Cannot delete a verified training record.']);
        }

        $trainingRecord->delete();

        return back()->with('success', 'Training record deleted.');
    }
}
