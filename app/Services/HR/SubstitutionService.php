<?php

namespace App\Services\HR;

use App\Models\Division;
use App\Models\HR\LeaveApplication;
use App\Models\HR\Substitution;
use App\Models\TravelRequest;
use App\Models\User;
use App\Services\PerformanceManagement\IPCRWorkflowService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class SubstitutionService
{
    public function __construct(private IPCRWorkflowService $ipcrWorkflow) {}

    /**
     * Create a pending-approval substitution grant tied to an approved
     * Leave/Travel absence.
     */
    public function nominate(User $originalUser, User $substituteUser, Model $absentable, ?string $notes = null): Substitution
    {
        [$start, $end] = $this->absentableDateRange($absentable);
        $errors = [];

        if (! $this->absentableIsApproved($absentable)) {
            $errors[] = 'The underlying leave/travel request is not approved.';
        }

        if ($originalUser->isSuperAdmin()) {
            $errors[] = 'Administrators cannot be substituted through this module.';
        }

        if ($originalUser->is($substituteUser)) {
            $errors[] = 'You cannot nominate yourself as your own substitute.';
        }

        if ($start && $end && $this->hasOverlappingApprovedLeave($substituteUser, $start, $end)) {
            $errors[] = 'The selected substitute has their own approved leave overlapping this period.';
        }

        if ($start && $end && $this->hasOverlappingGrant($originalUser, $start, $end)) {
            $errors[] = 'There is already an active or pending substitution for this period.';
        }

        if ($errors) {
            throw ValidationException::withMessages(['substitution' => $errors]);
        }

        return Substitution::create([
            'original_user_id' => $originalUser->id,
            'substitute_user_id' => $substituteUser->id,
            'absentable_type' => get_class($absentable),
            'absentable_id' => $absentable->id,
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'pending_approval',
            'nominated_by' => $originalUser->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Resolve who must approve a substitution nomination for this employee —
     * reuses the same Division Chief/AUH recommender resolution as the leave
     * approval workflow, falling back to the employee's Division Chief when
     * that resolves to nobody or to the employee themselves.
     */
    public function resolveApprover(User $originalUser): ?User
    {
        $recommender = $this->ipcrWorkflow->leaveRecommenderFor($originalUser);
        if ($recommender && (int) $recommender->id !== (int) $originalUser->id) {
            return $recommender;
        }

        $divisionChiefId = Division::where('id', $originalUser->division_id)->value('division_chief_id');
        if ($divisionChiefId && (int) $divisionChiefId !== (int) $originalUser->id) {
            return User::find($divisionChiefId);
        }

        return null;
    }

    /** @return array{0: ?string, 1: ?string} [start_date, end_date] as Y-m-d strings */
    private function absentableDateRange(Model $absentable): array
    {
        if ($absentable instanceof LeaveApplication) {
            return [$absentable->date_from?->toDateString(), $absentable->date_to?->toDateString()];
        }

        if ($absentable instanceof TravelRequest) {
            return [$absentable->start_date?->toDateString(), $absentable->end_date?->toDateString()];
        }

        return [null, null];
    }

    private function absentableIsApproved(Model $absentable): bool
    {
        if ($absentable instanceof LeaveApplication) {
            return $absentable->status === 'approved';
        }

        if ($absentable instanceof TravelRequest) {
            return in_array($absentable->status, [
                'ocd_approved', 'transport_arranged', 'cash_advance_processing',
                'dv_created', 'released', 'completed',
            ], true);
        }

        return false;
    }

    private function hasOverlappingApprovedLeave(User $user, string $start, string $end): bool
    {
        return LeaveApplication::where('user_id', $user->id)
            ->where('status', 'approved')
            ->where('date_from', '<=', $end)
            ->where('date_to', '>=', $start)
            ->exists();
    }

    private function hasOverlappingGrant(User $originalUser, string $start, string $end): bool
    {
        return Substitution::where('original_user_id', $originalUser->id)
            ->approvedOrPending()
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->exists();
    }
}
