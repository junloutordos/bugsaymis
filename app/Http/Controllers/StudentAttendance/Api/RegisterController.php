<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Mail\MobileEmailVerificationMail;
use App\Models\MobileEmailOtp;
use App\Models\StudentAttendance\ParentContact;
use App\Models\StudentCredential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/**
 * AtlasGo email+password registration. Neither path creates a row in the
 * main Atlas `users` table — parents are persisted to `parent_contacts`,
 * students to `student_credentials` (the legacy `students` table is
 * bulk re-imported and treated as read-only).
 */
class RegisterController extends Controller
{
    /** POST /api/mobile/register (parent) */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:200'],
            'email'                 => ['required', 'email', 'unique:parent_contacts,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        $belongsToStudent = DB::table('students')
            ->whereRaw('LOWER(TRIM(student_email)) = ?', [strtolower(trim($validated['email']))])
            ->exists();

        if ($belongsToStudent) {
            return response()->json([
                'message' => 'This email is registered as a student account. Please use the Student sign-in option instead.',
                'errors'  => ['email' => ['This email is registered as a student account.']],
            ], 422);
        }

        ParentContact::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status'   => 'pending_verification',
        ]);

        $this->sendOtp($validated['email'], $validated['name']);

        return response()->json([
            'message' => 'Registration successful. Check your email for the 6-digit verification code.',
        ]);
    }

    /** POST /api/mobile/verify-email */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'string', 'digits:6'],
        ]);

        $record = MobileEmailOtp::where('email', $validated['email'])
            ->where('otp_hash', hash('sha256', $validated['otp']))
            ->where('expires_at', '>', now())
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Invalid or expired verification code.'], 422);
        }

        $record->delete();

        $studentCredential = StudentCredential::where('email', $validated['email'])->first();

        if ($studentCredential) {
            $studentCredential->update([
                'status'            => 'active',
                'email_verified_at' => now(),
            ]);

            return response()->json(['message' => 'Email verified. You can now sign in.']);
        }

        $parentContact = ParentContact::where('email', $validated['email'])->first();

        if (! $parentContact) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $parentContact->update([
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        return response()->json(['message' => 'Email verified. You can now sign in.']);
    }

    /** POST /api/mobile/student/register */
    public function registerStudent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_email' => ['required', 'email'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        // Verify the email belongs to a student in the system
        $student = DB::table('students')
            ->where('student_email', $validated['student_email'])
            ->first(['id', 'firstname', 'lastname']);

        $genericMessage = 'If that email belongs to a registered student, a verification code has been sent.';

        if (! $student) {
            return response()->json(['message' => $genericMessage]);
        }

        // Prevent duplicate accounts — same generic response to prevent enumeration
        if (StudentCredential::where('student_id', $student->id)->exists()
            || StudentCredential::where('email', $validated['student_email'])->exists()) {
            return response()->json(['message' => $genericMessage]);
        }

        $name = trim("{$student->firstname} {$student->lastname}");

        StudentCredential::create([
            'student_id' => $student->id,
            'email'      => $validated['student_email'],
            'password'   => Hash::make($validated['password']),
            'status'     => 'pending_verification',
        ]);

        $this->sendOtp($validated['student_email'], $name);

        return response()->json([
            'message' => 'Registration successful. Check your school email for the 6-digit verification code.',
        ]);
    }

    /** POST /api/mobile/resend-verification */
    public function resendVerification(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);

        $studentCredential = StudentCredential::where('email', $validated['email'])
            ->where('status', 'pending_verification')
            ->first();

        $parentContact = $studentCredential ? null : ParentContact::where('email', $validated['email'])
            ->where('status', 'pending_verification')
            ->first();

        $name = $studentCredential
            ? DB::table('students')->where('id', $studentCredential->student_id)->value('firstname')
            : $parentContact?->name;

        // Same response whether an account exists or not (prevent enumeration)
        if ($studentCredential || $parentContact) {
            $this->sendOtp($validated['email'], (string) $name);
        }

        return response()->json([
            'message' => 'If that email is awaiting verification, a new code has been sent.',
        ]);
    }

    private function sendOtp(string $email, string $name): void
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        MobileEmailOtp::where('email', $email)->delete();

        MobileEmailOtp::create([
            'email'      => $email,
            'otp_hash'   => hash('sha256', $otp),
            'expires_at' => now()->addMinutes(15),
        ]);

        Mail::to($email)->send(new MobileEmailVerificationMail($name, $otp));
    }
}
