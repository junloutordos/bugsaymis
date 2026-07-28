<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Services\StudentAttendance\StudentGoogleLinkGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session()->has('student_pisaysystemID')) {
            return redirect()->route('student-portal.dashboard');
        }

        return Inertia::render('StudentPortal/Login');
    }

    /**
     * POST /student-portal/auth/firebase
     * Called by the frontend after Firebase signInWithPopup succeeds.
     * Body: { email, name, uid }
     */
    public function handleFirebaseAuth(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'name'  => ['required', 'string'],
            'uid'   => ['required', 'string'],
        ]);

        $email = strtolower(trim($request->email));

        if (! str_ends_with($email, '@crc.pshs.edu.ph')) {
            return response()->json([
                'success' => false,
                'message' => 'Only official PSHS-CRC accounts (@crc.pshs.edu.ph) are allowed.',
            ], 403);
        }

        // Check if already linked to a student record
        $link = DB::table('student_google_links')
            ->where('google_email', $email)
            ->first();

        if ($link) {
            $student = DB::table('students')
                ->where('pisaysystemID', $link->pisaysystemID)
                ->first();

            if ($student) {
                $this->startSession($student, $email);

                return response()->json([
                    'success'     => true,
                    'redirect_to' => route('student-portal.dashboard'),
                ]);
            }

            // Stale link — delete and ask to re-link
            DB::table('student_google_links')->where('google_email', $email)->delete();
        }

        // First time — store Google info in session and send to link page
        session()->put('student_portal_google_email', $email);
        session()->put('student_portal_google_name', $request->name);

        return response()->json([
            'success'    => true,
            'needs_link' => true,
            'redirect_to'=> route('student-portal.link'),
        ]);
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

        $student = DB::table('students')
            ->where('pisaysystemID', $pisayID)
            ->whereIn('status', ['Enrolled', 'Grade13'])
            ->first();

        if (! $student) {
            return back()->withErrors([
                'pisaysystemID' => 'No active student record found for that ID. Make sure you are currently enrolled and the ID is correct.',
            ]);
        }

        $mismatch = StudentGoogleLinkGuard::checkMismatch($student, $googleEmail);

        if ($mismatch) {
            return back()->withErrors(['pisaysystemID' => $mismatch]);
        }

        // Prevent one pisaysystemID from being linked to multiple Google accounts
        $existingLink = DB::table('student_google_links')
            ->where('pisaysystemID', $pisayID)
            ->where('google_email', '!=', $googleEmail)
            ->exists();

        if ($existingLink) {
            return back()->withErrors([
                'pisaysystemID' => 'This PISAY ID is already linked to another Google account. Contact the Guidance Office if this is an error.',
            ]);
        }

        DB::table('student_google_links')->updateOrInsert(
            ['google_email' => $googleEmail],
            ['pisaysystemID' => $pisayID, 'linked_at' => now()]
        );

        StudentGoogleLinkGuard::backfillEmailIfBlank($student, $googleEmail);

        session()->forget(['student_portal_google_email', 'student_portal_google_name']);

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
        ]);

        return redirect()->route('student-portal.login')
            ->with('success', 'You have been signed out.');
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function startSession(object $student, string $googleEmail): void
    {
        $gradeLevel = \App\Services\StudentPortal\GradeLevel::compute((int) $student->batch);

        session()->put('student_pisaysystemID', $student->pisaysystemID);
        session()->put('student_grade_level', $gradeLevel);
        session()->put('student_name', trim("{$student->lastname}, {$student->firstname}"));
        session()->put('student_google_email', $googleEmail);
        session()->regenerate();
    }
}
