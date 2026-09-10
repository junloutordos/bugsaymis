<?php

namespace App\Services\PerformanceManagement;

use App\Mail\CommitteeAssignmentMail;
use App\Models\Committee;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Mail;

/**
 * Central dispatch point for every committee lifecycle notification
 * (bell + email) and its audit-trail entry. Reuses the existing generic
 * NotificationService::notifyUser() (bell/push) and AuditLogger (audit_logs
 * table) — no new notification or audit infrastructure, just the
 * committee-specific call sites for both.
 */
class CommitteeNotificationService
{
    public function assignmentAdded(Committee $committee, User $member, string $role, float $loadUnits): void
    {
        $this->notify(
            $member, $committee,
            requestType: 'Committee Assignment',
            newStatus: 'Added to committee',
            role: $role,
            loadUnits: $loadUnits,
            subjectLine: "You've been added to {$committee->name}",
            headerTitle: 'Committee Assignment',
            lead: "You have been added to the <strong>{$committee->name}</strong> committee.",
        );

        AuditLogger::log([
            'action'         => 'committee_member_added',
            'auditable_type' => Committee::class,
            'auditable_id'   => $committee->id,
            'new_values'     => ['user_id' => $member->id, 'role' => $role, 'load_units' => $loadUnits],
        ]);
    }

    public function assignmentRemoved(Committee $committee, User $member, string $previousRole): void
    {
        $this->notify(
            $member, $committee,
            requestType: 'Committee Assignment',
            newStatus: 'Removed from committee',
            role: $previousRole,
            loadUnits: null,
            subjectLine: "You've been removed from {$committee->name}",
            headerTitle: 'Committee Assignment',
            lead: "You have been removed from the <strong>{$committee->name}</strong> committee.",
        );

        AuditLogger::log([
            'action'         => 'committee_member_removed',
            'auditable_type' => Committee::class,
            'auditable_id'   => $committee->id,
            'old_values'     => ['user_id' => $member->id, 'role' => $previousRole],
        ]);
    }

    public function roleChanged(Committee $committee, User $member, string $oldRole, string $newRole, float $loadUnits): void
    {
        $this->notify(
            $member, $committee,
            requestType: 'Committee Assignment',
            newStatus: "Role changed to {$newRole}",
            role: $newRole,
            loadUnits: $loadUnits,
            subjectLine: "Your role in {$committee->name} has changed",
            headerTitle: 'Committee Role Changed',
            lead: "Your role in the <strong>{$committee->name}</strong> committee has changed from <strong>{$oldRole}</strong> to <strong>{$newRole}</strong>.",
        );

        AuditLogger::log([
            'action'         => 'committee_role_changed',
            'auditable_type' => Committee::class,
            'auditable_id'   => $committee->id,
            'old_values'     => ['user_id' => $member->id, 'role' => $oldRole],
            'new_values'     => ['user_id' => $member->id, 'role' => $newRole],
        ]);
    }

    public function ratingReceived(Committee $committee, User $member, float $rowAverage): void
    {
        $this->notify(
            $member, $committee,
            requestType: 'Committee Rating',
            newStatus: 'Rated ' . number_format($rowAverage, 2),
            role: null,
            loadUnits: null,
            subjectLine: "You've been rated for {$committee->name}",
            headerTitle: 'Committee Rating Received',
            lead: "You have received a new rating for your committee accomplishment in <strong>{$committee->name}</strong>.",
            extraLabel: 'Average Rating',
            extraValue: number_format($rowAverage, 2),
        );
    }

    public function revoked(Committee $committee, ?string $reason, iterable $affectedMembers): void
    {
        foreach ($affectedMembers as $member) {
            $this->notify(
                $member, $committee,
                requestType: 'Committee Revoked',
                newStatus: 'Committee revoked',
                role: null,
                loadUnits: null,
                subjectLine: "{$committee->name} has been revoked",
                headerTitle: 'Committee Revoked',
                lead: "The <strong>{$committee->name}</strong> committee has been revoked and is no longer active.",
                extraLabel: $reason ? 'Reason' : null,
                extraValue: $reason,
            );
        }

        AuditLogger::log([
            'action'         => 'committee_revoked',
            'auditable_type' => Committee::class,
            'auditable_id'   => $committee->id,
            'new_values'     => ['reason' => $reason],
        ]);
    }

    public function amended(Committee $oldCommittee, Committee $newCommittee, iterable $affectedMembers): void
    {
        foreach ($affectedMembers as $member) {
            $this->notify(
                $member, $newCommittee,
                requestType: 'Committee Amended',
                newStatus: 'Committee amended',
                role: null,
                loadUnits: null,
                subjectLine: "{$oldCommittee->name} has been amended",
                headerTitle: 'Committee Amended',
                lead: "The <strong>{$oldCommittee->name}</strong> committee has been amended. A new version, <strong>{$newCommittee->name}</strong>, is now active.",
            );
        }

        AuditLogger::log([
            'action'         => 'committee_amended',
            'auditable_type' => Committee::class,
            'auditable_id'   => $oldCommittee->id,
            'new_values'     => ['amended_into_committee_id' => $newCommittee->id],
        ]);
    }

    public function vacancyAlert(User $admin, Committee $committee, int $activeCount, int $maxMembers): void
    {
        NotificationService::notifyUser(
            $admin,
            'Committee Vacancy',
            $committee->name,
            "Below capacity ({$activeCount}/{$maxMembers})",
            route('pm-committees.show', $committee->id),
            "This committee has {$activeCount} active member(s) out of a target of {$maxMembers}."
        );
    }

    private function notify(
        User $recipient,
        Committee $committee,
        string $requestType,
        string $newStatus,
        ?string $role,
        ?float $loadUnits,
        string $subjectLine,
        string $headerTitle,
        string $lead,
        ?string $extraLabel = null,
        ?string $extraValue = null,
    ): void {
        NotificationService::notifyUser(
            $recipient,
            $requestType,
            $committee->name,
            $newStatus,
            route('pm-committees.show', $committee->id),
        );

        try {
            Mail::to($recipient->email)->send(new CommitteeAssignmentMail(
                committee: $committee,
                recipientName: $recipient->name,
                subjectLine: $subjectLine,
                headerTitle: $headerTitle,
                lead: $lead,
                role: $role,
                loadUnits: $loadUnits,
                extraLabel: $extraLabel,
                extraValue: $extraValue,
            ));
        } catch (\Throwable $e) {
            logger()->error('Failed to send committee assignment email', ['user_id' => $recipient->id, 'error' => $e->getMessage()]);
        }
    }
}
