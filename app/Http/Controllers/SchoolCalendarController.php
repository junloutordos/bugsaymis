<?php

namespace App\Http\Controllers;

use App\Models\SchoolCalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SchoolCalendarController extends Controller
{
    // ── GET /academic-calendar ──────────────────────────────────────────────────

    public function index(Request $request)
    {
        $year = (int) $request->query('year', now()->year);

        $events = SchoolCalendarEvent::whereYear('date', $year)
            ->orderBy('date')
            ->get(['id', 'date', 'event_type', 'grade_level', 'title']);

        return Inertia::render('SchoolCalendar/Index', [
            'year'   => $year,
            'events' => $events,
        ]);
    }

    // ── POST /academic-calendar ─────────────────────────────────────────────────

    public function store(Request $request)
    {
        $data = $request->validate([
            'date'        => ['required', 'date'],
            'event_type'  => ['required', 'in:holiday,suspension'],
            'grade_level' => ['nullable', 'integer', 'between:7,12'],
            'title'       => ['required', 'string', 'max:255'],
        ]);

        SchoolCalendarEvent::create([...$data, 'created_by' => Auth::id()]);

        return back()->with('success', 'Calendar event added.');
    }

    // ── DELETE /academic-calendar/{event} ───────────────────────────────────────

    public function destroy(SchoolCalendarEvent $event)
    {
        $event->delete();

        return back()->with('success', 'Calendar event removed.');
    }
}
