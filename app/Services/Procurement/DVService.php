<?php

namespace App\Services\Procurement;

use App\Enums\ApprovalStep;
use App\Models\Procurement\DisbursementVoucher;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SnapshotService;
use Illuminate\Support\Facades\DB;

class DVService
{
    public function __construct(private SnapshotService $snapshots) {}

    private function ref(DisbursementVoucher $dv): string
    {
        return $dv->dv_number ?? "DV #{$dv->id}";
    }

    private function url(DisbursementVoucher $dv): string
    {
        return route('dv.show', $dv->id);
    }

    public function recordDelivery(DisbursementVoucher $dv, User $officer, array $data): void
    {
        DB::transaction(function () use ($dv, $officer, $data) {
            $dv->update(array_merge($data, [
                'status'              => 'delivery_accepted',
                'supply_officer_id'   => $officer->id,
                'delivery_accepted_at' => now(),
            ]));
        });

        NotificationService::notifyUser(
            $dv->creator, 'DV', $this->ref($dv),
            'Delivery accepted — ready to prepare DV',
            $this->url($dv),
        );
    }

    public function prepareDv(DisbursementVoucher $dv, User $user, array $data): void
    {
        DB::transaction(function () use ($dv, $user, $data) {
            $dv->update(array_merge($data, [
                'status'        => 'preparing',
                'dv_prepared_by' => $user->id,
                'dv_prepared_at' => now(),
            ]));
        });
    }

    public function dcElog(DisbursementVoucher $dv, User $dc): void
    {
        DB::transaction(function () use ($dv, $dc) {
            $dv->update([
                'division_chief_id'      => $dc->id,
                'division_chief_elog_at' => now(),
            ]);
            $this->snapshots->recordApproval($dv, ApprovalStep::DV_DIVISION_CHIEF, 1, 'approved', $dc);
        });
    }

    public function forwardToBookkeeper(DisbursementVoucher $dv, User $clerk): void
    {
        DB::transaction(function () use ($dv, $clerk) {
            $dv->update([
                'status'           => 'pending_bookkeeper',
                'division_clerk_id' => $clerk->id,
                'division_clerk_at' => now(),
            ]);
        });

        foreach (User::havingRole('Bookkeeper')->get() as $bk) {
            NotificationService::notifyUser($bk, 'DV', $this->ref($dv), 'Ready for completeness check', $this->url($dv));
        }
    }

    public function bookkeeperAction(DisbursementVoucher $dv, User $bk, string $action, string $remarks = ''): void
    {
        DB::transaction(function () use ($dv, $bk, $action, $remarks) {
            if ($action === 'forward') {
                $dv->update([
                    'status'             => 'pending_accountant',
                    'bookkeeper_id'      => $bk->id,
                    'bookkeeper_at'      => now(),
                    'bookkeeper_remarks' => $remarks,
                ]);
                $this->snapshots->recordApproval($dv, ApprovalStep::DV_BOOKKEEPER, 2, 'approved', $bk, $remarks);
                foreach (User::havingRole('Accountant')->get() as $acc) {
                    NotificationService::notifyUser($acc, 'DV', $this->ref($dv), 'Forwarded for review', $this->url($dv));
                }
            } else {
                $dv->update([
                    'status'                   => 'returned_bookkeeper',
                    'bookkeeper_id'            => $bk->id,
                    'bookkeeper_returned_at'   => now(),
                    'bookkeeper_return_reason' => $remarks,
                ]);
                NotificationService::notifyUser(
                    $dv->creator, 'DV', $this->ref($dv),
                    'Returned by Bookkeeper — incomplete documents',
                    $this->url($dv), $remarks,
                );
            }
        });
    }

    public function accountantAction(DisbursementVoucher $dv, User $acc, string $action, string $remarks = ''): void
    {
        DB::transaction(function () use ($dv, $acc, $action, $remarks) {
            if ($action === 'forward') {
                $dv->update([
                    'status'             => 'pending_ocd_sign',
                    'accountant_id'      => $acc->id,
                    'accountant_at'      => now(),
                    'accountant_remarks' => $remarks,
                ]);
                $this->snapshots->recordApproval($dv, ApprovalStep::DV_ACCOUNTANT, 3, 'approved', $acc, $remarks);
                foreach (User::havingRole('OCD')->get() as $ocd) {
                    NotificationService::notifyUser($ocd, 'DV', $this->ref($dv), 'Ready for CD signature', $this->url($dv));
                }
            } else {
                $dv->update([
                    'status'                    => 'returned_accountant',
                    'accountant_id'             => $acc->id,
                    'accountant_returned_at'    => now(),
                    'accountant_return_reason'  => $remarks,
                ]);
                NotificationService::notifyUser(
                    $dv->creator, 'DV', $this->ref($dv),
                    'Returned by Accountant — data error',
                    $this->url($dv), $remarks,
                );
            }
        });
    }

    public function ocdSign(DisbursementVoucher $dv, User $ocd): void
    {
        DB::transaction(function () use ($dv, $ocd) {
            $dv->update([
                'status'      => 'pending_cashier',
                'ocd_sign_id' => $ocd->id,
                'ocd_sign_at' => now(),
            ]);
            $this->snapshots->recordApproval($dv, ApprovalStep::DV_OCD_SIGN, 4, 'approved', $ocd);
        });

        foreach (User::havingRole('Cashier')->get() as $cashier) {
            NotificationService::notifyUser($cashier, 'DV', $this->ref($dv), 'Signed by CD — ready for payment processing', $this->url($dv));
        }
    }

    public function cashierProcess(DisbursementVoucher $dv, User $cashier, string $paymentMethod, float $amount): void
    {
        DB::transaction(function () use ($dv, $cashier, $paymentMethod, $amount) {
            $dv->update([
                'status'              => 'pending_ocd_payment',
                'cashier_id'          => $cashier->id,
                'cashier_processed_at' => now(),
                'cashier_amount'      => $amount,
                'payment_method'      => $paymentMethod,
            ]);
            $this->snapshots->recordApproval($dv, ApprovalStep::DV_CASHIER, 5, 'approved', $cashier);
        });

        foreach (User::havingRole('OCD')->get() as $ocd) {
            NotificationService::notifyUser($ocd, 'DV', $this->ref($dv), 'Payment prepared — awaiting CD signature', $this->url($dv));
        }
    }

    public function ocdPaymentSign(DisbursementVoucher $dv, User $ocd): void
    {
        DB::transaction(function () use ($dv, $ocd) {
            $dv->update([
                'status'               => 'released',
                'ocd_payment_sign_at'  => now(),
                'payment_released_at'  => now(),
            ]);
            $this->snapshots->recordApproval($dv, ApprovalStep::DV_OCD_PAYMENT, 6, 'approved', $ocd);
        });

        $cashier = $dv->cashier;
        if ($cashier) {
            NotificationService::notifyUser($cashier, 'DV', $this->ref($dv), 'CD signed — release payment to supplier', $this->url($dv));
        }
        NotificationService::notifyUser(
            $dv->creator, 'DV', $this->ref($dv), 'Payment released', $this->url($dv),
        );
    }

    public function resubmit(DisbursementVoucher $dv): void
    {
        DB::transaction(function () use ($dv) {
            $newStatus = match ($dv->status) {
                'returned_bookkeeper' => 'pending_bookkeeper',
                'returned_accountant' => 'pending_accountant',
                default               => 'pending_bookkeeper',
            };
            $dv->update(['status' => $newStatus]);
        });
    }
}
