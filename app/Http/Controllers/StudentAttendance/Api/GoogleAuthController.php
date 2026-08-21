<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\StudentAttendance\StudentGoogleClientFactory;
use App\Services\StudentAttendance\StudentGoogleLinkGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Google sign-in for students on the AtlasGo app — mirrors the
 * /student-portal Firebase flow: official @crc.pshs.edu.ph accounts only,
 * shared student_google_links table (link once, works on web + mobile).
 *
 * Students authenticate directly against the `students` table — no row is
 * ever created in the main Atlas `users` table, so a student account can
 * never sign in to Atlas proper (see Auth\GoogleAuthController's guard).
 *
 * The app sends the Google ID token; we verify it server-side against
 * Google's certs (audience = the Firebase web client ID the app requests
 * via serverClientId), so no Firebase Admin SDK round-trip is needed.
 */
class GoogleAuthController extends Controller
{
    public function __construct(private StudentGoogleClientFactory $googleClientFactory)
    {
    }

    /**
     * POST /api/mobile/student/google-login
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token'    => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $payload = $this->verifyIdToken($validated['id_token']);

        if (! $payload) {
            return response()->json(['message' => 'Google sign-in could not be verified. Please try again.'], 401);
        }

        $email = strtolower(trim($payload['email'] ?? ''));

        if (! ($payload['email_verified'] ?? false) || ! str_ends_with($email, '@crc.pshs.edu.ph')) {
            return response()->json([
                'message' => 'Only official PSHS-CRC accounts (@crc.pshs.edu.ph) are allowed.',
            ], 403);
        }

        $link = DB::table('student_google_links')->where('google_email', $email)->first();

        if ($link) {
            $student = DB::table('students')->where('pisaysystemID', $link->pisaysystemID)->first();

            if ($student) {
                return $this->issueStudentSession($student, $email, $payload['name'] ?? $email, $validated['device_name']);
            }

            // Stale link — drop it and fall through to the link step
            DB::table('student_google_links')->where('google_email', $email)->delete();
        }

        return response()->json([
            'needs_link' => true,
            'email'      => $email,
            'message'    => 'First sign-in: enter your PISAY ID to link this account.',
        ]);
    }

    /**
     * POST /api/mobile/student/google-link
     * First-time linking: same rules as the web portal link page.
     */
    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_token'      => ['required', 'string'],
            'pisaysystemID' => ['required', 'string', 'max:20'],
            'device_name'   => ['required', 'string', 'max:100'],
        ]);

        $payload = $this->verifyIdToken($validated['id_token']);

        if (! $payload) {
            return response()->json(['message' => 'Google sign-in could not be verified. Please sign in again.'], 401);
        }

        $email = strtolower(trim($payload['email'] ?? ''));

        if (! ($payload['email_verified'] ?? false) || ! str_ends_with($email, '@crc.pshs.edu.ph')) {
            return response()->json([
                'message' => 'Only official PSHS-CRC accounts (@crc.pshs.edu.ph) are allowed.',
            ], 403);
        }

        $pisayID = trim($validated['pisaysystemID']);

        $student = DB::table('students')
            ->where('pisaysystemID', $pisayID)
            ->whereIn('status', ['Enrolled', 'Grade13'])
            ->first();

        if (! $student) {
            return response()->json([
                'message' => 'No active student record found for that ID. Make sure you are currently enrolled and the ID is correct.',
            ], 422);
        }

        $mismatch = StudentGoogleLinkGuard::checkMismatch($student, $email);

        if ($mismatch) {
            return response()->json(['message' => $mismatch], 422);
        }

        // One PISAY ID per Google account (matches the web portal rule)
        $takenByOther = DB::table('student_google_links')
            ->where('pisaysystemID', $pisayID)
            ->where('google_email', '!=', $email)
            ->exists();

        if ($takenByOther) {
            return response()->json([
                'message' => 'This PISAY ID is already linked to another Google account. Contact the Guidance Office if this is an error.',
            ], 422);
        }

        DB::table('student_google_links')->updateOrInsert(
            ['google_email' => $email],
            ['pisaysystemID' => $pisayID, 'linked_at' => now()]
        );

        StudentGoogleLinkGuard::backfillEmailIfBlank($student, $email);

        return $this->issueStudentSession($student, $email, $payload['name'] ?? $email, $validated['device_name']);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    /**
     * Verify a Google ID token (signature, expiry, audience).
     * Returns the payload array, or null when invalid.
     */
    private function verifyIdToken(string $idToken): ?array
    {
        $client = $this->googleClientFactory->make();

        try {
            $payload = $client->verifyIdToken($idToken);
        } catch (\Throwable) {
            return null;
        }

        return $payload ?: null;
    }

    /**
     * Issue a Sanctum token directly against the Student model — no row is
     * created or looked up in the main Atlas `users` table.
     */
    private function issueStudentSession(object $student, string $email, string $name, string $deviceName): JsonResponse
    {
        $studentModel = Student::find($student->id);

        $studentModel->tokens()->where('name', $deviceName)->delete();
        $token = $studentModel->createToken($deviceName, ['mobile'])->plainTextToken;

        return response()->json([
            'token'      => $token,
            'role'       => 'student',
            'user'       => [
                'id'    => $studentModel->id,
                'name'  => trim("{$studentModel->firstname} {$studentModel->lastname}"),
                'email' => $email,
            ],
            'student_id' => $studentModel->id,
        ]);
    }
}
