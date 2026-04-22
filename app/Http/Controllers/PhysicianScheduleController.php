<?php

namespace App\Http\Controllers;

use App\Models\PhysicianSchedule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PhysicianScheduleController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');

        $query = PhysicianSchedule::query();
        if ($q) {
            $query->where('schedule_date', 'like', "%{$q}%")->orWhere('time_start', 'like', "%{$q}%")->orWhere('time_end', 'like', "%{$q}%");
        }

        $schedules = $query->orderBy('schedule_date')->orderBy('time_start')->paginate(10)->appends($request->all());

        return Inertia::render('Health/Schedule/Index', [
            'schedules' => $schedules,
            'q' => $q,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'schedule_date' => 'required|date',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
        ]);

        PhysicianSchedule::create($data);

        return redirect()->back()->with('success', 'Schedule created');
    }

    public function update(Request $request, PhysicianSchedule $schedule)
    {
        $data = $request->validate([
            'schedule_date' => 'required|date',
            'time_start' => 'required|date_format:H:i',
            'time_end' => 'required|date_format:H:i|after:time_start',
        ]);

        $schedule->update($data);

        return redirect()->back()->with('success', 'Schedule updated');
    }

    public function destroy(PhysicianSchedule $schedule)
    {
        $schedule->delete();
        return redirect()->back()->with('success', 'Schedule deleted');
    }
}
