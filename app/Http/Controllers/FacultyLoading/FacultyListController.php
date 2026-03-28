<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Office;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FacultyListController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('faculty_loading.manage');

        $query = User::with(['roles', 'division', 'office'])
            ->whereHas('roles', fn ($q) => $q->where('name', 'Faculty'))
            ->where('status', '<>', 'inactive')
            ->select('id', 'name', 'email', 'sex', 'badge_id', 'position', 'specialization',
                     'division_id', 'office_id', 'emp_category', 'on_study_leave', 'profile_picture');

        if ($request->filled('search')) {
            $s = $request->input('search');
            $query->where(function ($w) use ($s) {
                $w->where('name', 'like', "%{$s}%")
                  ->orWhere('badge_id', 'like', "%{$s}%")
                  ->orWhere('position', 'like', "%{$s}%")
                  ->orWhere('specialization', 'like', "%{$s}%");
            });
        }

        if ($request->filled('division_id')) {
            $query->where('division_id', $request->input('division_id'));
        }

        if ($request->filled('emp_category')) {
            $query->where('emp_category', $request->input('emp_category'));
        }

        $faculty = $query->orderBy('name')->get()->map(fn ($u) => [
            'id'             => $u->id,
            'name'           => $u->name,
            'email'          => $u->email,
            'sex'            => $u->sex,
            'badge_id'       => $u->badge_id,
            'position'       => $u->position,
            'specialization' => $u->specialization,
            'division'        => $u->division ? ['id' => $u->division->id, 'name' => $u->division->division_name] : null,
            'office'          => $u->office   ? ['id' => $u->office->id,   'name' => $u->office->name]           : null,
            'emp_category'    => $u->emp_category,
            'on_study_leave'  => (bool) $u->on_study_leave,
        ]);

        $divisions = Division::where('status', 'active')
            ->select('id', 'division_name')
            ->orderBy('division_name')
            ->get()
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->division_name]);

        $offices = Office::select('id', 'name', 'division_id')->orderBy('name')->get();

        return Inertia::render('FacultyLoading/FacultyList/Index', [
            'faculty'   => $faculty,
            'divisions' => $divisions,
            'offices'   => $offices,
            'filters'   => $request->only(['search', 'division_id', 'emp_category']),
        ]);
    }

    public function update(Request $request, $id): RedirectResponse
    {
        $this->authorize('faculty_loading.manage');

        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'            => 'required|string|max:255',
            'sex'             => 'nullable|in:Male,Female',
            'position'        => 'nullable|string|max:255',
            'specialization'  => 'nullable|string|max:150',
            'division_id'     => 'nullable|exists:divisions,id',
            'office_id'       => 'nullable|exists:offices,id',
            'emp_category'    => 'nullable|in:Plantilla Teaching,Plantilla Non-Teaching,COS Teaching,COS Non Teaching',
            'on_study_leave'  => 'boolean',
        ]);

        $user->update($data);

        return redirect()->route('faculty-loading.faculty-list')
            ->with('success', 'Faculty details updated successfully.');
    }
}
