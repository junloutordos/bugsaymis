<?php

namespace App\Services\Atlas;

use App\Models\User;
use App\Services\GoogleWorkspaceDirectoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 2 — auto-creates a real Google Workspace account for a newly
 * admitted student or newly hired employee, and writes the resulting
 * official email back to students.student_email / users.email.
 *
 * Never blocks the caller's transaction: any Workspace API failure is
 * logged and returns null so enrollment/hiring still succeeds — the
 * record is just left without an official email, catchable later by the
 * Phase 1 review queue or a manual retry.
 */
class WorkspaceProvisioningService
{
    private const DOMAIN = 'crc.pshs.edu.ph';

    public function __construct(
        private readonly GoogleWorkspaceDirectoryService $directory,
    ) {}

    /**
     * @return array{email: string, temp_password: string}|null
     */
    public function provisionStudent(int $studentId): ?array
    {
        $student = DB::table('students')->where('id', $studentId)->first();

        if (! $student) {
            return null;
        }

        $orgUnit = "/Batch {$student->batch} Students";

        $result = $this->createAccount($student->firstname, $student->lastname, $orgUnit, "{$student->batch}");

        if ($result === null) {
            return null;
        }

        DB::table('students')->where('id', $studentId)->update([
            'student_email' => $result['email'],
        ]);

        return $result;
    }

    /**
     * @return array{email: string, temp_password: string}|null
     */
    public function provisionEmployee(int $userId): ?array
    {
        $employee = User::find($userId);

        if (! $employee) {
            return null;
        }

        [$firstname, $lastname] = $this->splitName($employee->name);
        $isFaculty = in_array($employee->emp_category, ['Plantilla Teaching', 'COS Teaching'], true);
        $orgUnit = $isFaculty ? '/Faculty' : '/Staff';

        $result = $this->createAccount($firstname, $lastname, $orgUnit, null);

        if ($result === null) {
            return null;
        }

        $employee->update(['email' => $result['email']]);

        return $result;
    }

    /**
     * @return array{email: string, temp_password: string}|null
     */
    private function createAccount(string $firstname, string $lastname, string $orgUnitPath, ?string $suffix): ?array
    {
        try {
            $email = $this->uniqueEmail($firstname, $lastname, $suffix);
            $password = Str::password(16);

            $this->directory->createUser($email, $firstname, $lastname, $password, $orgUnitPath);

            return ['email' => $email, 'temp_password' => $password];
        } catch (\Throwable $e) {
            logger()->error('WorkspaceProvisioningService: account creation failed', [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'org_unit' => $orgUnitPath,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function uniqueEmail(string $firstname, string $lastname, ?string $suffix): string
    {
        $initial = $this->sanitize(mb_substr($firstname, 0, 1));
        $last = $this->sanitize($lastname);
        $base = $initial.$last;

        $candidate = $base.($suffix ?? '');
        $attempt = 2;

        while ($this->directory->emailExists("{$candidate}@".self::DOMAIN)
            || DB::table('students')->whereRaw('LOWER(student_email) = ?', [strtolower("{$candidate}@".self::DOMAIN)])->exists()
            || User::where('email', "{$candidate}@".self::DOMAIN)->exists()
        ) {
            $candidate = $base.$attempt.($suffix ?? '');
            $attempt++;
        }

        return strtolower("{$candidate}@".self::DOMAIN);
    }

    private function sanitize(string $value): string
    {
        return strtolower(preg_replace('/[^a-zA-Z]/', '', $value) ?? '');
    }

    /**
     * @return array{0: string, 1: string} [firstname, lastname]
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName)) ?: [];

        if (count($parts) < 2) {
            return [$fullName, $fullName];
        }

        $lastname = array_pop($parts);
        $firstname = implode(' ', $parts);

        return [$firstname, $lastname];
    }
}
