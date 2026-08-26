<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\EmployeeEssentialInfoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeEssentialInfoController extends Controller
{
    public function __construct(private readonly EmployeeEssentialInfoService $service) {}

    /**
     * Persist whichever of date_of_birth / residential address /
     * emergency contact were still missing, collected via the mandatory
     * post-login prompt. Only the fields the server determined were
     * actually missing are validated as required — already-complete PDS
     * data is never re-requested.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $missing = $this->service->missingFields($user);

        $rules = [];

        if (in_array('date_of_birth', $missing, true)) {
            $rules['date_of_birth'] = ['required', 'date', 'before_or_equal:today'];
        }

        if (in_array('residential_address', $missing, true)) {
            $rules['residential_house'] = ['required', 'string', 'max:255'];
            $rules['residential_street'] = ['nullable', 'string', 'max:255'];
            $rules['residential_subdivision'] = ['nullable', 'string', 'max:255'];
            $rules['residential_barangay'] = ['required', 'string', 'max:255'];
            $rules['residential_city'] = ['required', 'string', 'max:255'];
            $rules['residential_province'] = ['required', 'string', 'max:255'];
            $rules['residential_region'] = ['nullable', 'string', 'max:255'];
            $rules['residential_zip_code'] = ['nullable', 'string', 'max:20'];
        }

        if (in_array('emergency_contact', $missing, true)) {
            $rules['emergency_contact_name'] = ['required', 'string', 'max:150'];
            $rules['emergency_contact_phone'] = ['required', 'string', 'max:20'];
            $rules['emergency_contact_address'] = ['required', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        if (! empty($data)) {
            $this->service->save($user, $data);
        }

        $request->session()->forget('prompt_essential_info_setup');

        return back()->with('success', 'Your information has been saved.');
    }
}
