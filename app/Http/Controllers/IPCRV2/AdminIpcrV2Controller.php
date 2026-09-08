<?php

namespace App\Http\Controllers\IPCRV2;

use App\Http\Controllers\Controller;
use App\Mail\IpcrV2StatusMail;
use App\Models\IPCRV2\IpcrV2Record;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\IPCRV2\IpcrV2WorkflowService;
use App\Services\NotificationService;
use App\Services\PersonNameFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class AdminIpcrV2Controller extends Controller
{
    public function __construct(
        private \App\Services\IPCRV2\StrategicFunctionService $strategic = new \App\Services\IPCRV2\StrategicFunctionService(),
        private \App\Services\IPCRV2\IpcrV2SummaryService $summaryService = new \App\Services\IPCRV2\IpcrV2SummaryService(),
        private IpcrV2WorkflowService $workflow = new IpcrV2WorkflowService(),
        private DigitalSignatureService $sigService = new DigitalSignatureService(),
        private PersonNameFormatter $nameFormatter = new PersonNameFormatter()
    ) {}

    public function index()
    {
        $records = IpcrV2Record::with('user.pds.personalInfo', 'period')->latest('id')->get();
        $records->each(fn ($record) => $record->user->setAttribute('formatted_name', $this->nameFormatter->formal($record->user)));

        return Inertia::render('IPCRV2/AdminIpcrV2Index', ['records' => $records]);
    }

    public function show(Request $request, int $id)
    {
        $record = IpcrV2Record::with(['user.pds.personalInfo', 'coreItems', 'supportItems', 'period', 'coachingSessions', 'statusLogs.actor'])->findOrFail($id);
        $record->user->setAttribute('formatted_name', $this->nameFormatter->formal($record->user));
        $ocdUser = User::havingRole('OCD')->first();

        return Inertia::render('IPCRV2/AdminIpcrV2Show', [
            'ipcr' => $record,
            'strategicIndicators' => $this->strategic->currentIndicators(),
            'ocdUser' => $ocdUser ? [...$ocdUser->only('name', 'position'), 'formatted_name' => $this->nameFormatter->formal($ocdUser)] : null,
            'summary' => $this->summaryService->buildRows($record),
            'hasPin' => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
        ]);
    }

    public function reopen(Request $request, int $id)
    {
        $record = IpcrV2Record::findOrFail($id);
        abort_unless($record->isFinalized(), 422, 'This IPCR V2 is not locked — nothing to reopen.');

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
            'pin' => 'nullable|string',
        ]);

        $admin = $request->user();
        $this->sigService->assertSigningPin($admin, $data['pin'] ?? null);

        DB::transaction(function () use ($record, $admin, $data) {
            $fresh = IpcrV2Record::whereKey($record->id)->lockForUpdate()->firstOrFail();
            $fromStatus = $fresh->status;

            $fresh->update([
                'status' => IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
                'locked_at' => null,
                'locked_by_id' => null,
                'reopened_at' => now(),
                'reopened_by_id' => $admin->id,
                'reopen_reason' => $data['reason'],
            ]);

            $this->workflow->logAction(
                $fresh,
                $admin,
                'reopened',
                $fromStatus,
                IpcrV2WorkflowService::STATUS_SUBMITTED_PMT,
                $data['reason'],
                signedViaPin: ! empty($admin->signature_pin),
            );
        });

        $record->refresh()->loadMissing('user', 'period');
        $this->notifyReopen($record, $data['reason']);

        return back()->with('success', 'IPCR V2 reopened.');
    }

    private function notifyReopen(IpcrV2Record $record, string $reason): void
    {
        $recipients = array_filter(array_merge(
            [$record->user],
            User::havingRole('PMT')->get()->all(),
        ));

        foreach ($recipients as $recipient) {
            NotificationService::notifyUser(
                $recipient,
                'IPCR V2',
                $record->period?->label ?? "IPCR V2 #{$record->id}",
                'Reopened by Administrator',
                route('pmt-ipcr-v2.show', $record->id),
                $reason,
            );
            Mail::to($recipient->email)->queue(new IpcrV2StatusMail($record, $recipient, 'Reopened by Administrator', $reason));
        }
    }
}
