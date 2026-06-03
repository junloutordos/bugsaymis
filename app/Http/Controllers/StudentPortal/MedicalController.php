<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MedicalController extends Controller
{
    public function show()
    {
        $pisayID = session('student_pisaysystemID');
        $sy      = DB::table('school_years')->where('is_current', 1)->first();

        $existing = [
            'allergies'       => DB::table('student_allergies')
                ->where('pisaySystemID', $pisayID)->orderBy('created_at')->get()->toArray(),
            'immunizations'   => DB::table('student_immunization_history')
                ->where('pisaySystemID', $pisayID)->orderBy('date_administered')->get()->toArray(),
            'medical_history' => DB::table('student_medical_history')
                ->where('pisaySystemID', $pisayID)->orderBy('date_sustained')->get()->toArray(),
            'vitamins'        => DB::table('student_vitamins_history')
                ->where('pisaySystemID', $pisayID)->orderBy('date_taken')->get()->toArray(),
        ];

        $submitted = $sy
            ? DB::table('student_form_submissions')
                ->where('pisaysystemID', $pisayID)
                ->where('school_year_id', $sy->id)
                ->whereIn('section', ['allergies','immunizations','medical_history','vitamins'])
                ->pluck('submitted_at', 'section')->toArray()
            : [];

        return Inertia::render('StudentPortal/Medical', [
            'existing'       => $existing,
            'submitted'      => $submitted,
            'school_year'    => $sy?->name,
            'school_year_id' => $sy?->id,
        ]);
    }

    public function saveSection(Request $request, string $section)
    {
        $pisayID    = session('student_pisaysystemID');
        $gradeLevel = session('student_grade_level');
        $sy         = DB::table('school_years')->where('is_current', 1)->first();

        match ($section) {
            'allergies'       => $this->saveAllergies($request, $pisayID),
            'immunizations'   => $this->saveImmunizations($request, $pisayID),
            'medical_history' => $this->saveMedicalHistory($request, $pisayID),
            'vitamins'        => $this->saveVitamins($request, $pisayID),
            default           => abort(404),
        };

        if ($sy) {
            DB::table('student_form_submissions')->updateOrInsert(
                ['pisaysystemID' => $pisayID, 'school_year_id' => $sy->id, 'section' => $section],
                ['grade_level' => $gradeLevel, 'submitted_at' => now()]
            );
        }

        return back()->with('success', 'Records saved successfully.');
    }

    // ── Section savers ─────────────────────────────────────────────────────────

    private function saveAllergies(Request $request, string $pid): void
    {
        $data = $request->validate([
            'allergies'              => ['nullable', 'array'],
            'allergies.*.allergy'    => ['required', 'string', 'in:yes,no'],
            'allergies.*.medication' => ['nullable', 'string', 'max:255'],
        ]);

        DB::table('student_allergies')->where('pisaySystemID', $pid)->delete();
        foreach ($data['allergies'] ?? [] as $row) {
            DB::table('student_allergies')->insert([
                'pisaySystemID' => $pid,
                'allergy'       => $row['allergy'],
                'medication'    => $row['medication'] ?? null,
                'created_at'    => now(),
            ]);
        }
    }

    private function saveImmunizations(Request $request, string $pid): void
    {
        $data = $request->validate([
            'immunizations'                      => ['nullable', 'array'],
            'immunizations.*.vaccine'            => ['required', 'string', 'max:255'],
            'immunizations.*.date_administered'  => ['nullable', 'date'],
        ]);

        DB::table('student_immunization_history')->where('pisaySystemID', $pid)->delete();
        foreach ($data['immunizations'] ?? [] as $row) {
            DB::table('student_immunization_history')->insert([
                'pisaySystemID'   => $pid,
                'vaccine'         => $row['vaccine'],
                'date_administered' => $row['date_administered'] ?? null,
                'created_at'      => now(),
            ]);
        }
    }

    private function saveMedicalHistory(Request $request, string $pid): void
    {
        $data = $request->validate([
            'history'                         => ['nullable', 'array'],
            'history.*.disease'               => ['required', 'string', 'max:255'],
            'history.*.date_sustained'        => ['nullable', 'date'],
            'history.*.opd'                   => ['nullable', 'boolean'],
            'history.*.hospital_confinement'  => ['nullable', 'boolean'],
        ]);

        DB::table('student_medical_history')->where('pisaySystemID', $pid)->delete();
        foreach ($data['history'] ?? [] as $row) {
            DB::table('student_medical_history')->insert([
                'pisaySystemID'       => $pid,
                'disease'             => $row['disease'],
                'date_sustained'      => $row['date_sustained'] ?? null,
                'opd'                 => $row['opd'] ?? false,
                'hospital_confinement'=> $row['hospital_confinement'] ?? false,
                'created_at'          => now(),
            ]);
        }
    }

    private function saveVitamins(Request $request, string $pid): void
    {
        $data = $request->validate([
            'vitamins'              => ['nullable', 'array'],
            'vitamins.*.vitamin'    => ['required', 'string', 'max:255'],
            'vitamins.*.date_taken' => ['nullable', 'date'],
        ]);

        DB::table('student_vitamins_history')->where('pisaySystemID', $pid)->delete();
        foreach ($data['vitamins'] ?? [] as $row) {
            DB::table('student_vitamins_history')->insert([
                'pisaySystemID' => $pid,
                'vitamin'       => $row['vitamin'],
                'date_taken'    => $row['date_taken'] ?? null,
                'created_at'    => now(),
            ]);
        }
    }
}
