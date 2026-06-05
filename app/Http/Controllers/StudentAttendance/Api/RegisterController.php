<?php

namespace App\Http\Controllers\StudentAttendance\Api;

use App\Http\Controllers\Controller;
use App\Mail\MobileEmailVerificationMail;
use App\Models\MobileEmailOtp;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentMobileLink;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class RegisterController extends Controller
{
    /** POST /api/mobile/register */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:200'],
            'email'                 => ['required', 'email', 'unique:users,email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ]);

        $parentRole = Role::where('name', 'Parent')->firstOrFail();

        User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status'   => 'pending_verification',
            'role_id'  => (string) $parentRole->id,
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

        $user = User::where('email', $validated['email'])->first();

        if (! $user) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $user->update([
            'status'            => 'active',
            'email_verified_at' => now(),
        ]);

        $record->delete();

        // If this is a student account, create the mobile link now
        $studentRole = Role::where('name', 'Student')->first();
        if ($studentRole && (string) $user->role_id === (string) $studentRole->id) {
            $student = DB::table('students')
                ->where('student_email', $validated['email'])
                ->first(['id']);

            if ($student) {
                StudentMobileLink::firstOrCreate(
                    ['student_id' => $student->id],
                    ['user_id' => $user->id]
                );
            }
        }

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
        if (User::where('email', $validated['student_email'])->exists()) {
            return response()->json(['message' => $genericMessage]);
        }

        $studentRole = Role::where('name', 'Student')->firstOrFail();
        $name = trim("{$student->firstname} {$student->lastname}");

        User::create([
            'name'     => $name,
            'email'    => $validated['student_email'],
            'password' => Hash::make($validated['password']),
            'status'   => 'pending_verification',
            'role_id'  => (string) $studentRole->id,
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

        $user = User::where('email', $validated['email'])
            ->where('status', 'pending_verification')
            ->first();

        // Same response whether user exists or not (prevent enumeration)
        if ($user) {
            $this->sendOtp($validated['email'], $user->name);
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
