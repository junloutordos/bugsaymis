<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\WorkRequest;
use App\Models\Division;
use App\Models\Office;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class WorkRequestController extends Controller
{
    public function index()
    {
        $divisions = Division::where('status', 'active')->select('id', 'division_name')->get();
        $offices = Office::select('id', 'name', 'division_id')->get();
        $users = User::select('id', 'name')->get();

        $workRequests = WorkRequest::with(['division', 'office', 'assignedUser', 'requester', 'actedBy'])->get();

        return Inertia::render('GeneralServices/WorkRequest', [
            'divisions' => $divisions,
            'offices' => $offices,
            'users' => $users,
            'workRequests' => $workRequests,
        ]);
    }

    public function store(Request $request)
    {
        // Only accept fields intended at creation time. Assignment and completion
        // details are handled later by GSU Head, so they are not accepted here.
        $data = $request->validate([
            'issue' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:Low,Normal,High',
            'location_division_id' => 'nullable|exists:divisions,id',
            'location_office_id' => 'nullable|exists:offices,id',
            'expected_completion_date' => 'nullable|date',
        ]);

        $data['requester_id'] = Auth::id();
        $data['status'] = 'Pending';

        WorkRequest::create($data);

        return redirect()->route('work-requests.index')->with('success', 'Work request created.');
    }

    public function update(Request $request, WorkRequest $workRequest)
    {
        $data = $request->validate([
            'issue' => 'required|string|max:255',
            'category' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|string|in:Low,Normal,High',
            'location_division_id' => 'nullable|exists:divisions,id',
            'location_office_id' => 'nullable|exists:offices,id',
            'assigned_user_id' => 'nullable|exists:users,id',
            'acted_by_id' => 'nullable|exists:users,id',
            'expected_completion_date' => 'nullable|date',
            'action_taken' => 'nullable|string',
            'date_completed' => 'nullable|date',
            'status' => 'nullable|string',
        ]);


        $workRequest->update($data);

        return redirect()->route('work-requests.index')->with('success', 'Work request updated.');
    }

    public function destroy(WorkRequest $workRequest)
    {
        $workRequest->delete();
        return redirect()->route('work-requests.index')->with('success', 'Work request deleted.');
    }
}
