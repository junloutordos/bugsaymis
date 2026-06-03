<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session()->has('student_pisaysystemID')) {
            return redirect()->route('student-portal.dashboard');
        }

        return Inertia::render('StudentPortal/Login');
    }

    public function redirectToGoogle()
    {
        // Store intended destination so callback can redirect correctly
        session()->put('student_portal_oauth', true);

        return Socialite::driver('google')
            ->with(['hd' => 'crc.pshs.edu.ph'])
            ->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('student-portal.login')
                ->with('error', 'Google sign-in failed. Please try again.');
        }

        $googleEmail = strtolower(trim($googleUser->getEmail() ?? ''));

        // Enforce @crc.pshs.edu.ph domain
        if (! str_ends_with($googleEmail, '@crc.pshs.edu.ph')) {
            return redirect()->route('student-portal.login')
                ->with('error', 'Only official PSHS-CRC accounts (@crc.pshs.edu.ph) are allowed.');
        }

        // Check if this Google email is already linked to a student record
        $link = DB::table('student_google_links')
            ->where('google_email', $googleEmail)
            ->first();

        if ($link) {
            $student = DB::table('students')
                ->where('pisaysystemID', $link->pisaysystemID)
                ->first();

            if ($student) {
                $this->startSession($student, $googleEmail);
                return redirect()->route('student-portal.dashboard');
            }

            // Stale link (student record gone) — delete and re-link
            DB::table('student_google_links')->where('google_email', $googleEmail)->delete();
        }

        // First time: store Google email in session and ask for pisaysystemID
        session()->put('student_portal_google_email', $googleEmail);
        session()->put('student_portal_google_name', $googleUser->getName());

        return redirect()->route('student-portal.link');
    }

    public function showLink()
    {
        if (! session()->has('student_portal_google_email')) {
            return redirect()->route('student-portal.login');
        }

        if (session()->has('student_pisaysystemID')) {
            return redirect()->route('student-portal.dashboard');
        }

        return Inertia::render('StudentPortal/Link', [
            'google_name' => session('student_portal_google_name'),
        ]);
    }

    public function submitLink(Request $request)
    {
        $googleEmail = session('student_portal_google_email');

        if (! $googleEmail) {
            return redirect()->route('student-portal.login');
        }

        $validated = $request->validate([
            'pisaysystemID' => ['required', 'string', 'max:20'],
        ]);

        $pisayID = trim($validated['pisaysystemID']);

        // Look up student — pisaysystemID must exist and not already be linked
        // to a DIFFERENT Google email (prevents one student claiming another's record)
        $student = DB::table('students')
            ->where('pisaysystemID', $pisayID)
            ->whereIn('status', ['Enrolled', 'Grade13'])
            ->first();

        if (! $student) {
            return back()->withErrors([
                'pisaysystemID' => 'No active student record found for that ID. Make sure you are currently enrolled and the ID is correct.',
            ]);
        }

        // Check if this pisaysystemID is already linked to a different Google account
        $existingLink = DB::table('student_google_links')
            ->where('pisaysystemID', $pisayID)
            ->where('google_email', '!=', $googleEmail)
            ->exists();

        if ($existingLink) {
            return back()->withErrors([
                'pisaysystemID' => 'This PISAY ID is already linked to another Google account. Contact the Guidance Office if this is an error.',
            ]);
        }

        // Create the link
        DB::table('student_google_links')->updateOrInsert(
            ['google_email' => $googleEmail],
            ['pisaysystemID' => $pisayID, 'linked_at' => now()]
        );

        session()->forget(['student_portal_google_email', 'student_portal_google_name', 'student_portal_oauth']);

        $this->startSession($student, $googleEmail);

        return redirect()->route('student-portal.dashboard');
    }

    public function logout(Request $request)
    {
        $request->session()->forget([
            'student_pisaysystemID',
            'student_grade_level',
            'student_name',
            'student_google_email',
            'student_portal_google_email',
            'student_portal_google_name',
            'student_portal_oauth',
        ]);

        return redirect()->route('student-portal.login')
            ->with('success', 'You have been signed out.');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function startSession(object $student, string $googleEmail): void
    {
        $gradeLevel = $this->computeGradeLevel((int) $student->batch);

        session()->put('student_pisaysystemID', $student->pisaysystemID);
        session()->put('student_grade_level', $gradeLevel);
        session()->put('student_name', trim("{$student->lastname}, {$student->firstname}"));
        session()->put('student_google_email', $googleEmail);
        session()->regenerate();
    }

    private function computeGradeLevel(int $batch): int
    {
        $sy = DB::table('school_years')->where('is_current', 1)->first();

        if (! $sy) {
            return 0;
        }

        // School year name format: "2026-2027" → end year = 2027
        $syEndYear = (int) explode('-', $sy->name)[1];

        // grade = 12 − (batch − syEndYear)
        $grade = 12 - ($batch - $syEndYear);

        return max(7, min(12, $grade));
    }
}
