<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // Prompt digital signature setup once per login until completed
        // (flag consumed by HandleInertiaRequests on first page render).
        $user = $request->user();
        if (empty($user->electronic_signature) || empty($user->signature_pin)) {
            session(['prompt_signature_setup' => true]);
        }

        // Mandatory (non-dismissable) prompt for hire year/month, used to
        // generate employee_idno_new. Fires every login until answered.
        if (empty($user->employee_idno_new)) {
            session(['prompt_employee_id_setup' => true]);
        } elseif (! empty(app(\App\Services\HR\EmployeeEssentialInfoService::class)->missingFields($user))) {
            // Sequential — only flag the second mandatory prompt (DOB,
            // residential address, emergency contact) once the employee ID
            // prompt has already been resolved, so they never overlap.
            session(['prompt_essential_info_setup' => true]);
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
