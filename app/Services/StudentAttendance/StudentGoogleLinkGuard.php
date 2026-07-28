<?php

namespace App\Services\StudentAttendance;

use Illuminate\Support\Facades\DB;

/**
 * Shared identity checks for the "link my Google account to my PISAY ID"
 * step (mobile GoogleAuthController + web StudentPortal\AuthController).
 *
 * The student types their own PISAY ID after Google-authenticating with
 * their official @crc.pshs.edu.ph address. A typo in that ID used to
 * silently attach the session to a *different* student's record with no
 * verification at all — this guard closes that gap.
 */
class StudentGoogleLinkGuard
{
    /**
     * Returns an error message if $googleEmail is not allowed to claim
     * $student, or null if the link may proceed.
     */
    public static function checkMismatch(object $student, string $googleEmail): ?string
    {
        $onFile = strtolower(trim((string) ($student->student_email ?? '')));
        $incoming = strtolower(trim($googleEmail));

        if ($onFile !== '' && $onFile !== $incoming) {
            return 'That PISAY ID belongs to a different student account. Please double-check the ID and try again.';
        }

        return null;
    }

    /**
     * Once a link is verified via Google sign-in, backfill the student's
     * official email on file if it was never set.
     */
    public static function backfillEmailIfBlank(object $student, string $googleEmail): void
    {
        $onFile = trim((string) ($student->student_email ?? ''));

        if ($onFile === '') {
            DB::table('students')
                ->where('id', $student->id)
                ->update(['student_email' => strtolower(trim($googleEmail))]);
        }
    }
}
