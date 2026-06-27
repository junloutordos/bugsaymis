<?php

namespace App\Http\Controllers\ResidenceHall;

use App\Http\Controllers\Controller;
use App\Models\ResidenceHall\RhWaiver;
use App\Models\ResidenceHall\RhIntern;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RhWaiverController extends Controller
{
    public function save(Request $request, RhIntern $rhIntern)
    {
        $this->authorize('rh.interns.manage');

        $user = Auth::user();
        if (!$user->isSuperAdmin() && $user->rh_hall && $rhIntern->room && $rhIntern->room->residence_hall !== $user->rh_hall) {
            abort(403);
        }

        $data = $request->validate([
            'can_go_home_alone'     => 'required|boolean',
            'guardian_name'         => 'nullable|string|max:255',
            'guardian_contact'      => 'nullable|string|max:50',
            'signed_by_student_at'  => 'nullable|date',
            'signed_by_guardian_at' => 'nullable|date',
        ]);

        $data['rh_intern_id'] = $rhIntern->id;

        RhWaiver::updateOrCreate(
            ['rh_intern_id' => $rhIntern->id],
            $data
        );

        return back()->with('success', 'Waiver saved.');
    }
}
