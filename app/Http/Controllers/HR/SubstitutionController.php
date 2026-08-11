<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\HR\LeaveApplication;
use App\Models\HR\Substitution;
use App\Models\TravelRequest;
use App\Services\HR\SubstitutionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SubstitutionController extends Controller
{
    public function __construct(private SubstitutionService $substitutions) {}

    public function index(Request $request)
    {
        $user = Auth::user();

        $myNominations = Substitution::where('nominated_by', $user->id)
            ->with(['substitute:id,name', 'absentable'])
            ->latest()
            ->get();

        $mySubstitutions = Substitution::where('substitute_user_id', $user->id)
            ->approvedOrPending()
            ->with(['originalUser:id,name'])
            ->latest()
            ->get()
            ->map(fn (Substitution $s) => [
                ...$s->toArray(),
                'can_act_as' => $s->isWithinWindow(),
            ]);

        $forMyApproval = Substitution::where('status', 'pending_approval')
            ->with(['originalUser:id,name', 'substitute:id,name'])
            ->get()
            ->filter(function (Substitution $s) use ($user) {
                if ($user->isSuperAdmin() || $user->hasPermission('hr.substitution.approve')) {
                    return true;
                }
                $resolved = $this->substitutions->resolveApprover($s->originalUser);

                return $resolved && (int) $resolved->id === (int) $user->id;
            })
            ->values();

        return Inertia::render('HR/Substitutions/Index', [
            'myNominations' => $myNominations,
            'mySubstitutions' => $mySubstitutions,
            'forMyApproval' => $forMyApproval,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'substitute_user_id' => 'required|exists:users,id',
            'leave_application_id' => 'nullable|exists:leave_applications,id',
            'travel_request_id' => 'nullable|exists:travel_requests,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        abort_if(
            empty($data['leave_application_id']) === empty($data['travel_request_id']),
            422,
            'Provide exactly one of leave_application_id or travel_request_id.'
        );

        $absentable = ! empty($data['leave_application_id'])
            ? LeaveApplication::findOrFail($data['leave_application_id'])
            : TravelRequest::findOrFail($data['travel_request_id']);

        abort_unless(
            (int) $absentable->user_id === Auth::id() || (int) ($absentable->traveler_id ?? null) === Auth::id(),
            403,
            'You can only nominate a substitute for your own leave/travel.'
        );

        $substitute = \App\Models\User::findOrFail($data['substitute_user_id']);

        $this->substitutions->nominate(Auth::user(), $substitute, $absentable, $data['notes'] ?? null);

        return back()->with('success', 'Substitute nominated — awaiting approval.');
    }

    public function approve(Request $request, Substitution $substitution)
    {
        $request->validate(['remarks' => 'nullable|string|max:500']);

        $this->substitutions->approve($substitution, Auth::user(), $request->input('remarks'));

        return back()->with('success', 'Substitution approved.');
    }

    public function reject(Request $request, Substitution $substitution)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);

        $this->substitutions->reject($substitution, Auth::user(), $data['reason']);

        return back()->with('success', 'Substitution rejected.');
    }

    public function revoke(Request $request, Substitution $substitution)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $this->substitutions->revoke($substitution, Auth::user(), $data['reason'] ?? 'Revoked.');

        return back()->with('success', 'Substitution revoked.');
    }
}
