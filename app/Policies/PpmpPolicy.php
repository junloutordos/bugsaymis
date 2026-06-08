<?php

namespace App\Policies;

use App\Models\PPMP\Ppmp;
use App\Models\User;

class PpmpPolicy extends GenericPolicy
{
    /**
     * Super admin bypass — note: all can* props in the controller must also gate by
     * ppmp.status + ppmp.ppmp_type so SuperAdmin doesn't see all buttons simultaneously.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['ppmp.create', 'ppmp.view_all', 'ppmp.division_review',
            'ppmp.property_officer_review', 'ppmp.budget_officer_review', 'ppmp.approve']);
    }

    public function view(User $user, Ppmp $ppmp): bool
    {
        if ($user->hasPermission('ppmp.view_all')) {
            return true;
        }

        // Property Officer sees all Division PPMPs across divisions
        if ($user->hasPermission('ppmp.property_officer_review')
            && $ppmp->ppmp_type === Ppmp::PPMP_TYPE_DIVISION) {
            return true;
        }

        // Budget Officer and OCD see all Property PPMPs
        if ($user->hasAnyPermission(['ppmp.budget_officer_review', 'ppmp.approve'])
            && $ppmp->ppmp_type === Ppmp::PPMP_TYPE_PROPERTY) {
            return true;
        }

        return $user->division_id === $ppmp->division_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('ppmp.create');
    }

    public function update(User $user, Ppmp $ppmp): bool
    {
        if (!$ppmp->canEdit()) {
            return false;
        }

        // Division PPMPs edited by Division Chief
        if ($ppmp->ppmp_type === Ppmp::PPMP_TYPE_DIVISION) {
            return $user->hasPermission('ppmp.division_review')
                && $user->division_id === $ppmp->division_id;
        }

        // Property PPMPs edited by Property Officer
        if ($ppmp->ppmp_type === Ppmp::PPMP_TYPE_PROPERTY) {
            return $user->hasPermission('ppmp.property_officer_review');
        }

        // Unit PPMPs
        if (!$user->hasPermission('ppmp.create')) {
            return false;
        }

        return $user->division_id === $ppmp->division_id;
    }

    public function delete(User $user, Ppmp $ppmp): bool
    {
        if ($ppmp->status !== Ppmp::STATUS_DRAFT) {
            return false;
        }

        if ($ppmp->ppmp_type === Ppmp::PPMP_TYPE_DIVISION) {
            return $user->hasPermission('ppmp.division_review')
                && $user->division_id === $ppmp->division_id;
        }

        if ($ppmp->ppmp_type === Ppmp::PPMP_TYPE_PROPERTY) {
            return $user->hasPermission('ppmp.property_officer_review');
        }

        if (!$user->hasPermission('ppmp.create')) {
            return false;
        }

        return $user->division_id === $ppmp->division_id;
    }

    public function submit(User $user, Ppmp $ppmp): bool
    {
        if (!$ppmp->canSubmit()) {
            return false;
        }

        if ($ppmp->ppmp_type === Ppmp::PPMP_TYPE_DIVISION) {
            return $user->hasPermission('ppmp.division_review')
                && $user->division_id === $ppmp->division_id;
        }

        if ($ppmp->ppmp_type === Ppmp::PPMP_TYPE_PROPERTY) {
            return $user->hasPermission('ppmp.property_officer_review');
        }

        // Unit PPMP
        if (!$user->hasPermission('ppmp.submit')) {
            return false;
        }

        return $user->division_id === $ppmp->division_id;
    }

    public function divisionReview(User $user, Ppmp $ppmp): bool
    {
        if (!$user->hasPermission('ppmp.division_review')) {
            return false;
        }

        if (!$ppmp->canDivisionReview()) {
            return false;
        }

        return $user->division_id === $ppmp->division_id;
    }

    public function propertyOfficerReview(User $user, Ppmp $ppmp): bool
    {
        return $user->hasPermission('ppmp.property_officer_review')
            && $ppmp->canPropertyOfficerReview();
    }

    public function budgetOfficerReview(User $user, Ppmp $ppmp): bool
    {
        return $user->hasPermission('ppmp.budget_officer_review')
            && $ppmp->canBudgetOfficerReview();
    }

    public function ocdApprove(User $user, Ppmp $ppmp): bool
    {
        return $user->hasPermission('ppmp.approve')
            && $ppmp->canOcdApprove();
    }

    public function ocdReturn(User $user, Ppmp $ppmp): bool
    {
        return $this->ocdApprove($user, $ppmp);
    }

    public function submitToDbm(User $user, Ppmp $ppmp): bool
    {
        return $user->hasPermission('ppmp.budget_officer_review')
            && $ppmp->canSubmitToDbm();
    }

    public function approve(User $user, Ppmp $ppmp): bool
    {
        if (!$user->hasPermission('ppmp.approve')) {
            return false;
        }

        return $ppmp->canApprove();
    }

    public function returnForRevision(User $user, Ppmp $ppmp): bool
    {
        return $this->approve($user, $ppmp);
    }

    public function consolidate(User $user): bool
    {
        return $user->hasPermission('ppmp.consolidate');
    }

    public function export(User $user, Ppmp $ppmp): bool
    {
        if (!$user->hasPermission('ppmp.export')) {
            return false;
        }

        if ($user->hasPermission('ppmp.view_all')) {
            return true;
        }

        return $user->division_id === $ppmp->division_id;
    }
}
