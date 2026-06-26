<?php

namespace App\Http\Controllers\Discipline;

use App\Http\Controllers\Controller;
use App\Models\Discipline\DisciplineOffense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DisciplineOffenseController extends Controller
{
    public function index()
    {
        return Inertia::render('Discipline/Offenses/Index', [
            'offenses' => DisciplineOffense::orderBy('level')
                ->orderBy('sort_order')->orderBy('title')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateOffense($request);
        DisciplineOffense::create($data);

        return back()->with('success', 'Offense added to the catalog.');
    }

    public function update(Request $request, DisciplineOffense $offense)
    {
        $offense->update($this->validateOffense($request));

        return back()->with('success', 'Offense updated.');
    }

    public function destroy(DisciplineOffense $offense)
    {
        $offense->delete();

        return back()->with('success', 'Offense removed.');
    }

    private function validateOffense(Request $request): array
    {
        return $request->validate([
            'code'             => ['nullable', 'string', 'max:50'],
            'category'         => ['nullable', 'string', 'max:255'],
            'level'            => ['required', 'integer', 'in:1,2,3'],
            'title'            => ['required', 'string', 'max:255'],
            'description'      => ['nullable', 'string'],
            'default_sanction' => ['nullable', 'string'],
            'sort_order'       => ['nullable', 'integer'],
            'is_active'        => ['nullable', 'boolean'],
        ]);
    }
}
