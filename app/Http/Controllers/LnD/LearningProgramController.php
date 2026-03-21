<?php

namespace App\Http\Controllers\LnD;

use App\Http\Controllers\Controller;
use App\Models\LearningProgram;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LearningProgramController extends Controller
{
    // ── Index ──────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $this->authorize('lnd.view');

        $query = LearningProgram::with(['creator:id,name', 'sessions'])
            ->withCount('sessions');

        if ($search = $request->input('search')) {
            $query->where(fn ($q) =>
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('provider', 'like', "%{$search}%")
                  ->orWhere('competency_area', 'like', "%{$search}%")
            );
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($year = $request->input('year')) {
            $query->forYear((int) $year);
        }

        $programs = $query->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('LnD/LearningPrograms/Index', [
            'programs' => $programs,
            'filters'  => $request->only(['search', 'type', 'status', 'year']),
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $this->authorize('lnd.create');

        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'type'             => ['required', Rule::in(['mandatory', 'technical', 'leadership', 'functional'])],
            'competency_area'  => ['nullable', 'string', 'max:255'],
            'target_position'  => ['nullable', 'string', 'max:255'],
            'provider'         => ['nullable', 'string', 'max:255'],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date', 'after_or_equal:start_date'],
            'hours'            => ['nullable', 'integer', 'min:1'],
            'budget'           => ['nullable', 'numeric', 'min:0'],
            'status'           => ['required', Rule::in(['planned', 'ongoing', 'completed', 'cancelled'])],
        ]);

        $validated['created_by'] = Auth::id();

        $program = LearningProgram::create($validated);

        return back()->with('success', "Learning program '{$program->title}' created.");
    }

    // ── Show ───────────────────────────────────────────────────────────────────

    public function show(LearningProgram $learningProgram)
    {
        $this->authorize('lnd.view');

        $learningProgram->load([
            'creator:id,name',
            'sessions' => fn ($q) => $q->withCount('participants')->orderBy('session_date'),
            'sessions.participants.employee:id,name',
        ]);

        return Inertia::render('LnD/LearningPrograms/Show', [
            'program' => $learningProgram,
        ]);
    }

    // ── Update ─────────────────────────────────────────────────────────────────

    public function update(Request $request, LearningProgram $learningProgram)
    {
        $this->authorize('lnd.edit');

        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'type'             => ['required', Rule::in(['mandatory', 'technical', 'leadership', 'functional'])],
            'competency_area'  => ['nullable', 'string', 'max:255'],
            'target_position'  => ['nullable', 'string', 'max:255'],
            'provider'         => ['nullable', 'string', 'max:255'],
            'start_date'       => ['nullable', 'date'],
            'end_date'         => ['nullable', 'date', 'after_or_equal:start_date'],
            'hours'            => ['nullable', 'integer', 'min:1'],
            'budget'           => ['nullable', 'numeric', 'min:0'],
            'status'           => ['required', Rule::in(['planned', 'ongoing', 'completed', 'cancelled'])],
        ]);

        $learningProgram->update($validated);

        return back()->with('success', 'Learning program updated.');
    }

    // ── Destroy ────────────────────────────────────────────────────────────────

    public function destroy(LearningProgram $learningProgram)
    {
        $this->authorize('lnd.delete');

        if ($learningProgram->sessions()->exists()) {
            return back()->withErrors(['error' => 'Cannot delete a program that has training sessions.']);
        }

        $learningProgram->delete();

        return back()->with('success', 'Learning program deleted.');
    }

    // ── Update Status ──────────────────────────────────────────────────────────

    public function updateStatus(Request $request, LearningProgram $learningProgram)
    {
        $this->authorize('lnd.edit');

        $validated = $request->validate([
            'status' => ['required', Rule::in(['planned', 'ongoing', 'completed', 'cancelled'])],
        ]);

        $learningProgram->update($validated);

        return back()->with('success', "Status updated to {$validated['status']}.");
    }
}
