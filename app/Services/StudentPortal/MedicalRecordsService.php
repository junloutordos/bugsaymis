<?php

namespace App\Services\StudentPortal;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Shared logic for the student medical record forms — used by both the
 * student web portal and the mobile API.
 */
class MedicalRecordsService
{
    /**
     * Data needed to render / pre-fill the medical forms.
     */
    public function showData(string $pisayID): array
    {
        $sy = DB::table('school_years')->where('is_current', 1)->first();

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

        return [
            'existing'       => $existing,
            'submitted'      => FormSubmissions::map($pisayID, $sy?->id, FormSubmissions::MEDICAL_SECTIONS),
            'school_year'    => $sy?->name,
            'school_year_id' => $sy?->id,
        ];
    }

    /**
     * Validate and persist one medical section, then mark it submitted
     * for the current school year.
     */
    public function saveSection(Request $request, string $section, string $pisayID, int $gradeLevel): void
    {
        match ($section) {
            'allergies'       => $this->saveAllergies($request, $pisayID),
            'immunizations'   => $this->saveImmunizations($request, $pisayID),
            'medical_history' => $this->saveMedicalHistory($request, $pisayID),
            'vitamins'        => $this->saveVitamins($request, $pisayID),
            default           => abort(404),
        };

        FormSubmissions::mark($pisayID, $gradeLevel, $section);
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
