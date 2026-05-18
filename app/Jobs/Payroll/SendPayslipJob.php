<?php

namespace App\Jobs\Payroll;

use App\Models\Payroll\PayrollBatch;
use App\Models\Payroll\PayrollEmail;
use App\Models\Payroll\PayrollItem;
use App\Services\Payroll\PayrollPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendPayslipJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60; // seconds between retries

    public function __construct(public int $payrollEmailId) {}

    public function handle(PayrollPdfService $pdfService): void
    {
        $emailRecord = PayrollEmail::with([
            'item.batch',
            'item.employee',
        ])->findOrFail($this->payrollEmailId);

        if ($emailRecord->status === 'sent') return;

        $emailRecord->increment('attempts');
        $emailRecord->update(['status' => 'sending']);

        $item  = $emailRecord->item;
        $batch = $item->batch;
        $emp   = $item->employee;

        if (!$emp || !$emp->email) {
            $emailRecord->update(['status' => 'failed', 'last_error' => 'No employee email.']);
            return;
        }

        try {
            [$preparedBy, $certifiedBy] = $pdfService->signatories();

            $firstName = explode(' ', $emp->name)[0];
            $subject   = $emailRecord->subject;
            $bcc       = $emailRecord->bcc_email;
            $sendType  = $emailRecord->send_type;

            // For release groups: collect per-month items for the breakdown table
            $releaseItems = null;
            if ($batch->release_id && $item->matched_user_id) {
                $siblingIds   = PayrollBatch::where('release_id', $batch->release_id)
                    ->orderBy('year')->orderBy('month')
                    ->pluck('id');
                $releaseItems = PayrollItem::whereIn('batch_id', $siblingIds)
                    ->where('matched_user_id', $item->matched_user_id)
                    ->with('batch')
                    ->get()
                    ->sortBy(fn($i) => $i->year * 100 + $i->month)
                    ->values();
            }

            $combined         = $pdfService->buildCombined($item);
            // For release groups, the banner shows the total accumulated amount
            $notificationItem = $batch->release_id ? $combined : $item;

            $htmlBody = view('payroll.payslip_email', [
                'item'             => $combined,
                'notificationItem' => $notificationItem,
                'batch'            => $batch,
                'preparedBy'       => $preparedBy,
                'certifiedBy'      => $certifiedBy,
                'firstName'        => $firstName,
                'sendType'         => $sendType,
                'releaseItems'     => $releaseItems,
            ])->render();

            $pdfBytes  = $pdfService->generate($item);
            $pdfName   = $pdfService->filename($item);

            Mail::send([], [], function ($message) use ($emp, $subject, $bcc, $htmlBody, $pdfBytes, $pdfName) {
                $message->to($emp->email, $emp->name)
                    ->subject($subject)
                    ->html($htmlBody)
                    ->attachData($pdfBytes, $pdfName, ['mime' => 'application/pdf']);

                if ($bcc) {
                    $message->bcc($bcc);
                }
            });

            $emailRecord->update([
                'status'  => 'sent',
                'sent_at' => now(),
            ]);

        } catch (\Throwable $e) {
            $emailRecord->update([
                'status'     => $this->attempts() >= $this->tries ? 'failed' : 'queued',
                'last_error' => substr($e->getMessage(), 0, 500),
            ]);

            throw $e; // Let the queue retry
        }
    }

    public function failed(\Throwable $e): void
    {
        PayrollEmail::find($this->payrollEmailId)?->update([
            'status'     => 'failed',
            'last_error' => substr($e->getMessage(), 0, 500),
        ]);
    }
}
