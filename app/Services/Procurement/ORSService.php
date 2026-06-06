<?php

namespace App\Services\Procurement;

use App\Enums\ApprovalStep;
use App\Models\Procurement\OblRequest;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SnapshotService;
use Illuminate\Support\Facades\DB;

class ORSService
{
    public function __construct(private SnapshotService $snapshots) {}

    public function submit(OblRequest $ors, User $creator): void
    {
        DB::transaction(fn () => $ors->update(['status' => 'pending_dc']));

        foreach (User::havingRole('DivisionChief')->get() as $dc) {
            NotificationService::notifyUser(
                $dc, 'ORS', $ors->ors_number ?? "ORS #{$ors->id}",
                'Awaiting your signature',
                route('ors.show', $ors->id),
            );
        }
    }

    public function dcSign(OblRequest $ors, User $dc): void
    {
        DB::transaction(function () use ($ors, $dc) {
            $ors->update([
                'status'           => 'pending_bo',
                'division_chief_id' => $dc->id,
                'division_chief_at' => now(),
            ]);
            $this->snapshots->recordApproval($ors, ApprovalStep::ORS_DIVISION_CHIEF, 1, 'approved', $dc);
        });

        $this->notifyBudgetOfficers($ors, "Signed by Division Chief — awaiting your review");
        NotificationService::notifyUser(
            $ors->creator, 'ORS', $ors->ors_number ?? "ORS #{$ors->id}",
            'Signed by Division Chief — please log and forward',
            route('ors.show', $ors->id),
        );
    }

    public function logAndForward(OblRequest $ors, User $user, array $logData): void
    {
        DB::transaction(function () use ($ors, $logData) {
            $ors->update(array_merge($logData, ['logged_at' => now()]));
        });
    }

    public function budgetOfficerAction(OblRequest $ors, User $bo, string $action, string $orsNumber = '', string $remarks = ''): void
    {
        DB::transaction(function () use ($ors, $bo, $action, $orsNumber, $remarks) {
            if ($action === 'approve') {
                $ors->update([
                    'status'                 => 'pending_bookkeeper',
                    'ors_number'             => $orsNumber ?: $ors->ors_number,
                    'budget_officer_id'      => $bo->id,
                    'budget_officer_at'      => now(),
                    'budget_officer_remarks' => $remarks,
                ]);
                $this->snapshots->recordApproval($ors, ApprovalStep::ORS_BUDGET_OFFICER, 2, 'approved', $bo, $remarks);
                $this->notifyBookkeepers($ors, 'Ready for completeness verification');
            } else {
                $ors->update([
                    'status'                           => 'returned_bo',
                    'budget_officer_id'                => $bo->id,
                    'budget_officer_returned_at'       => now(),
                    'budget_officer_return_reason'     => $remarks,
                ]);
                NotificationService::notifyUser(
                    $ors->creator, 'ORS', $ors->ors_number ?? "ORS #{$ors->id}",
                    'Returned by Budget Officer',
                    route('ors.show', $ors->id),
                    $remarks,
                );
            }
        });
    }

    public function bookkeeperAction(OblRequest $ors, User $bk, string $action, string $remarks = ''): void
    {
        DB::transaction(function () use ($ors, $bk, $action, $remarks) {
            if ($action === 'forward') {
                $ors->update([
                    'status'             => 'pending_accountant',
                    'bookkeeper_id'      => $bk->id,
                    'bookkeeper_at'      => now(),
                    'bookkeeper_remarks' => $remarks,
                ]);
                $this->snapshots->recordApproval($ors, ApprovalStep::ORS_BOOKKEEPER, 3, 'approved', $bk, $remarks);
                $this->notifyAccountants($ors, 'Forwarded to you for review');
            } else {
                $ors->update([
                    'status'                    => 'returned_bookkeeper',
                    'bookkeeper_id'             => $bk->id,
                    'bookkeeper_returned_at'    => now(),
                    'bookkeeper_return_reason'  => $remarks,
                ]);
                NotificationService::notifyUser(
                    $ors->creator, 'ORS', $ors->ors_number ?? "ORS #{$ors->id}",
                    'Returned by Bookkeeper — incomplete documents',
                    route('ors.show', $ors->id),
                    $remarks,
                );
            }
        });
    }

    public function accountantSign(OblRequest $ors, User $acc, string $remarks = ''): void
    {
        DB::transaction(function () use ($ors, $acc, $remarks) {
            $ors->update([
                'status'            => 'pending_ocd',
                'accountant_id'     => $acc->id,
                'accountant_at'     => now(),
                'accountant_remarks' => $remarks,
            ]);
            $this->snapshots->recordApproval($ors, ApprovalStep::ORS_ACCOUNTANT, 4, 'approved', $acc, $remarks);
        });

        foreach (User::havingRole('OCD')->get() as $ocd) {
            NotificationService::notifyUser(
                $ocd, 'ORS', $ors->ors_number ?? "ORS #{$ors->id}",
                'Ready for Campus Director signature',
                route('ors.show', $ors->id),
            );
        }
    }

    public function ocdSign(OblRequest $ors, User $ocd): void
    {
        DB::transaction(function () use ($ors, $ocd) {
            $ors->update([
                'status' => 'at_canvaser',
                'ocd_id' => $ocd->id,
                'ocd_at' => now(),
            ]);
            $this->snapshots->recordApproval($ors, ApprovalStep::ORS_OCD, 5, 'approved', $ocd);
        });

        NotificationService::notifyUser(
            $ors->creator, 'ORS', $ors->ors_number ?? "ORS #{$ors->id}",
            'Signed by Campus Director — at Canvaser',
            route('ors.show', $ors->id),
        );
    }

    public function canvaserReceive(OblRequest $ors, User $canvaser): void
    {
        DB::transaction(function () use ($ors, $canvaser) {
            $ors->update([
                'status'      => 'completed',
                'canvaser_id' => $canvaser->id,
                'canvaser_at' => now(),
            ]);
            $this->snapshots->recordApproval($ors, ApprovalStep::ORS_CANVASER, 6, 'approved', $canvaser);
        });

        NotificationService::notifyUser(
            $ors->creator, 'ORS', $ors->ors_number ?? "ORS #{$ors->id}",
            'Completed — forwarded to supplier',
            route('ors.show', $ors->id),
        );
    }

    public function resubmit(OblRequest $ors): void
    {
        // End User resubmits after return
        DB::transaction(function () use ($ors) {
            $newStatus = match ($ors->status) {
                'returned_bo'         => 'pending_bo',
                'returned_bookkeeper' => 'pending_bookkeeper',
                default               => 'pending_bo',
            };
            $ors->update(['status' => $newStatus]);
        });
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function notifyBudgetOfficers(OblRequest $ors, string $message): void
    {
        foreach (User::havingRole('Budget Officer')->get() as $bo) {
            NotificationService::notifyUser($bo, 'ORS', $ors->ors_number ?? "ORS #{$ors->id}", $message, route('ors.show', $ors->id));
        }
    }

    private function notifyBookkeepers(OblRequest $ors, string $message): void
    {
        foreach (User::havingRole('Bookkeeper')->get() as $bk) {
            NotificationService::notifyUser($bk, 'ORS', $ors->ors_number ?? "ORS #{$ors->id}", $message, route('ors.show', $ors->id));
        }
    }

    private function notifyAccountants(OblRequest $ors, string $message): void
    {
        foreach (User::havingRole('Accountant')->get() as $acc) {
            NotificationService::notifyUser($acc, 'ORS', $ors->ors_number ?? "ORS #{$ors->id}", $message, route('ors.show', $ors->id));
        }
    }
}
