<?php

namespace App\Http\Controllers;

use App\Models\CsmResponse;
use App\Models\Office;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OfficeQrSurveyController extends Controller
{
    /**
     * Public, no-auth: render the anonymous Client Satisfaction Survey for
     * the office identified by its opaque QR token.
     */
    public function show(string $token)
    {
        $office = Office::where('qr_survey_token', $token)->first();

        abort_if(! $office, 404);
        abort_if(! $office->qr_survey_enabled, 404);

        return Inertia::render('CSM/PublicOfficeSurvey', [
            'office' => [
                'id'    => $office->id,
                'name'  => $office->name,
                'token' => $office->qr_survey_token,
            ],
        ]);
    }

    /**
     * Public, no-auth, rate-limited: store an anonymous CSM response for the office.
     */
    public function store(Request $request, string $token)
    {
        $office = Office::where('qr_survey_token', $token)->first();

        abort_if(! $office, 404);
        abort_if(! $office->qr_survey_enabled, 404);

        $validated = $request->validate([
            'client_type'          => 'required|in:citizen,business,government',
            'sex'                  => 'nullable|in:male,female',
            'age'                  => 'nullable|integer|min:1|max:120',
            'region_of_residence'  => 'required|string|max:100',
            'date_of_transaction'  => 'required|date|before_or_equal:today',
            'service_availed'      => 'required|array|min:1',
            'service_availed.*'    => 'string|max:100',
            'service_availed_other'=> 'nullable|string|max:255',
            'cc1'                  => 'required|integer|between:1,4',
            'cc2'                  => 'nullable|integer|between:1,5',
            'cc3'                  => 'nullable|integer|between:1,4',
            'sqd0'                 => 'required|integer|between:1,6',
            'sqd1'                 => 'required|integer|between:1,6',
            'sqd2'                 => 'required|integer|between:1,6',
            'sqd3'                 => 'required|integer|between:1,6',
            'sqd4'                 => 'required|integer|between:1,6',
            'sqd5'                 => 'required|integer|between:1,6',
            'sqd6'                 => 'required|integer|between:1,6',
            'sqd7'                 => 'required|integer|between:1,6',
            'sqd8'                 => 'required|integer|between:1,6',
            'suggestions'          => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($validated, $office) {
            CsmResponse::create([
                'respondable_type'      => Office::class,
                'respondable_id'        => $office->id,
                'user_id'               => null,
                'client_type'           => $validated['client_type'],
                'sex'                   => $validated['sex'] ?? null,
                'age'                   => $validated['age'] ?? null,
                'region_of_residence'   => $validated['region_of_residence'],
                'date_of_transaction'   => $validated['date_of_transaction'],
                'office_availed'        => $office->name,
                'service_availed'       => $validated['service_availed'],
                'service_availed_other' => $validated['service_availed_other'] ?? null,
                'cc1'                   => $validated['cc1'],
                'cc2'                   => in_array($validated['cc1'], [1, 2, 3]) ? ($validated['cc2'] ?? null) : null,
                'cc3'                   => in_array($validated['cc1'], [1, 2, 3]) ? ($validated['cc3'] ?? null) : null,
                'sqd0' => $validated['sqd0'], 'sqd1' => $validated['sqd1'], 'sqd2' => $validated['sqd2'],
                'sqd3' => $validated['sqd3'], 'sqd4' => $validated['sqd4'], 'sqd5' => $validated['sqd5'],
                'sqd6' => $validated['sqd6'], 'sqd7' => $validated['sqd7'], 'sqd8' => $validated['sqd8'],
                'suggestions' => $validated['suggestions'] ?? null,
            ]);
        });

        return back()->with('success', 'Thank you! Your Client Satisfaction Survey has been submitted.');
    }
}
