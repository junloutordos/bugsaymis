<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Services\HR\EmployeeIdNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmployeeIdSetupController extends Controller
{
    public function __construct(private readonly EmployeeIdNumberService $service) {}

    /**
     * Persist the hire year/month collected from the mandatory post-login
     * prompt, generate the employee's ID-card number, and clear the prompt.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $currentYear = (int) now()->format('Y');

        $data = $request->validate([
            'hired_year'  => ['required', 'integer', 'min:1980', 'max:' . $currentYear],
            'hired_month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        // Idempotent — if the user already has an ID number (e.g. resubmitted
        // via a duplicate request), do not generate a second one.
        if (empty($user->employee_idno_new)) {
            $this->service->generateFor($user, (int) $data['hired_year'], (int) $data['hired_month']);
        }

        $request->session()->forget('prompt_employee_id_setup');

        return back()->with('success', 'Employee ID number generated successfully.');
    }
}
