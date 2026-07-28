<?php

namespace App\Services\Atlas;

use App\Models\Atlas\WorkspaceSyncProposal;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\GoogleWorkspaceDirectoryService;
use Illuminate\Support\Facades\DB;

/**
 * Matches the real Google Workspace directory against students/employees on
 * file and stages proposed student_email / users.email corrections for
 * manual review (see project CLAUDE.md — "Google Admin API" plan, Phase 1).
 *
 * Nothing here writes to students/users directly except approve() — scan()
 * only ever writes to the workspace_sync_proposals staging table.
 */
class WorkspaceSyncService
{
    public function __construct(
        private readonly GoogleWorkspaceDirectoryService $directory,
    ) {}

    /**
     * Pull the live Workspace directory, match it against active students and
     * employees, and (re)stage pending proposals. Existing pending proposals
     * are refreshed; already-reviewed ones are left untouched.
     *
     * @return array{scanned_workspace_users: int, students_proposed: int, employees_proposed: int}
     */
    public function scan(): array
    {
        $workspaceUsers = $this->directory->listUsers(5000);

        $studentsProposed = $this->scanStudents($workspaceUsers);
        $employeesProposed = $this->scanEmployees($workspaceUsers);

        return [
            'scanned_workspace_users' => count($workspaceUsers),
            'students_proposed' => $studentsProposed,
            'employees_proposed' => $employeesProposed,
        ];
    }

    private function scanStudents(array $workspaceUsers): int
    {
        // Only Workspace accounts sitting under a "Batch XXXX Students" OU are candidates.
        $studentWsUsers = array_values(array_filter(
            $workspaceUsers,
            fn (array $u) => (bool) preg_match('/^\/Batch (\d{4}) Students$/', $u['orgUnitPath'], $m)
        ));

        $students = DB::table('students')
            ->whereIn('status', ['Enrolled', 'Grade13'])
            ->get(['id', 'firstname', 'lastname', 'batch', 'student_email']);

        $proposed = 0;

        foreach ($students as $student) {
            $match = $this->bestMatch(workspaceUsers: $studentWsUsers, dbFullName: trim("{$student->firstname} {$student->lastname}"), expectedOrgUnit: "/Batch {$student->batch} Students");

            if (! $match) {
                continue;
            }

            if ($this->alreadyCorrect($student->student_email, $match['primaryEmail'])) {
                continue;
            }

            WorkspaceSyncProposal::updateOrCreate(
                ['subject_type' => 'student', 'subject_id' => $student->id],
                [
                    'subject_name'   => trim("{$student->firstname} {$student->lastname}"),
                    'current_email'  => $student->student_email,
                    'proposed_email' => strtolower($match['primaryEmail']),
                    'org_unit_path'  => $match['orgUnitPath'],
                    'confidence'     => $match['confidence'],
                    'status'         => 'pending',
                ],
                // Don't touch rows already reviewed unless the proposal actually changed.
                ['status' => 'pending']
            );

            $proposed++;
        }

        return $proposed;
    }

    private function scanEmployees(array $workspaceUsers): int
    {
        $employeeWsUsers = array_values(array_filter(
            $workspaceUsers,
            fn (array $u) => in_array($u['orgUnitPath'], ['/Faculty', '/Staff'], true)
        ));

        $employees = User::where('account_type', 'employee')
            ->where('status', '<>', 'inactive')
            ->get(['id', 'name', 'email']);

        $proposed = 0;

        foreach ($employees as $employee) {
            $match = $this->bestMatch(workspaceUsers: $employeeWsUsers, dbFullName: $employee->name, expectedOrgUnit: null);

            if (! $match) {
                continue;
            }

            if ($this->alreadyCorrect($employee->email, $match['primaryEmail'])) {
                continue;
            }

            WorkspaceSyncProposal::updateOrCreate(
                ['subject_type' => 'employee', 'subject_id' => $employee->id],
                [
                    'subject_name'   => $employee->name,
                    'current_email'  => $employee->email,
                    'proposed_email' => strtolower($match['primaryEmail']),
                    'org_unit_path'  => $match['orgUnitPath'],
                    'confidence'     => $match['confidence'],
                    'status'         => 'pending',
                ],
                ['status' => 'pending']
            );

            $proposed++;
        }

        return $proposed;
    }

    /**
     * Approve a staged proposal: writes the verified email back to the
     * student/employee record and audit-logs the change.
     */
    public function approve(WorkspaceSyncProposal $proposal, User $reviewer): void
    {
        if ($proposal->subject_type === 'student') {
            $before = DB::table('students')->where('id', $proposal->subject_id)->value('student_email');

            DB::table('students')->where('id', $proposal->subject_id)->update([
                'student_email' => $proposal->proposed_email,
            ]);

            AuditLogger::log([
                'action' => 'edit',
                'auditable_type' => 'App\\Models\\Student',
                'auditable_id' => $proposal->subject_id,
                'old_values' => ['student_email' => $before],
                'new_values' => ['student_email' => $proposal->proposed_email],
            ]);
        } else {
            $employee = User::findOrFail($proposal->subject_id);
            $before = $employee->email;

            $employee->update(['email' => $proposal->proposed_email]);

            AuditLogger::log([
                'action' => 'edit',
                'auditable_type' => User::class,
                'auditable_id' => $employee->id,
                'old_values' => ['email' => $before],
                'new_values' => ['email' => $proposal->proposed_email],
            ]);
        }

        $proposal->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    public function reject(WorkspaceSyncProposal $proposal, User $reviewer): void
    {
        $proposal->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    // ── Matching ─────────────────────────────────────────────────────────────

    /**
     * @param array<int, array{primaryEmail: string, fullName: string, orgUnitPath: string}> $workspaceUsers
     * @return array{primaryEmail: string, orgUnitPath: string, confidence: string}|null
     */
    private function bestMatch(array $workspaceUsers, string $dbFullName, ?string $expectedOrgUnit): ?array
    {
        $dbTokens = $this->tokens($dbFullName);

        if (count($dbTokens) < 2) {
            return null; // not enough signal (blank/single-word name on file)
        }

        $lastToken = end($dbTokens);
        $firstToken = $dbTokens[0];

        $best = null;

        foreach ($workspaceUsers as $wsUser) {
            $wsTokens = $this->tokens($wsUser['fullName']);

            if (empty($wsTokens)) {
                continue;
            }

            $lastnameMatches = in_array($lastToken, $wsTokens, true);
            $firstnameMatches = in_array($firstToken, $wsTokens, true);

            if (! $lastnameMatches) {
                continue;
            }

            $confidence = $firstnameMatches ? 'high' : 'medium';

            // Downgrade if the student isn't in the OU we'd expect for their batch —
            // still worth reviewing (retention/transfer), just less certain.
            if ($expectedOrgUnit !== null && $wsUser['orgUnitPath'] !== $expectedOrgUnit) {
                $confidence = 'medium';
            }

            // Prefer the first "high" match found; otherwise keep the first "medium".
            if ($confidence === 'high') {
                return [
                    'primaryEmail' => $wsUser['primaryEmail'],
                    'orgUnitPath' => $wsUser['orgUnitPath'],
                    'confidence' => 'high',
                ];
            }

            $best ??= [
                'primaryEmail' => $wsUser['primaryEmail'],
                'orgUnitPath' => $wsUser['orgUnitPath'],
                'confidence' => 'medium',
            ];
        }

        return $best;
    }

    /**
     * @return array<int, string>
     */
    private function tokens(string $name): array
    {
        $normalized = strtoupper(trim(preg_replace('/[^\p{L}\s]/u', ' ', $name) ?? ''));
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return array_values(array_filter(explode(' ', $normalized)));
    }

    private function alreadyCorrect(?string $currentEmail, string $proposedEmail): bool
    {
        $current = strtolower(trim((string) $currentEmail));

        return $current !== '' && $current === strtolower(trim($proposedEmail));
    }
}
