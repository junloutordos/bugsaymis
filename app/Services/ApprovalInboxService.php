<?php

namespace App\Services;

use App\Models\ComputerLabScheduleApproval;
use App\Models\Division;
use App\Models\Facility;
use App\Models\FacilityRequest;
use App\Models\FacultyLoading\ClassScheduleApprovalBatch;
use App\Models\HR\LeaveApplication;
use App\Models\ITJobRequest;
use App\Models\MessengerialRequest;
use App\Models\PMS;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VehicleRequest;
use App\Models\WorkRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApprovalInboxService
{
    public function __construct(private User $user) {}

    /**
     * Sum all role-applicable pending counts for the sidebar badge.
     */
    public function totalPendingCount(): int
    {
        $total = 0;
        foreach ($this->getPendingItems() as $tab) {
            $total += $tab['count'];
        }

        return $total;
    }

    /**
     * Build the full tabs array with normalised items for each role.
     * Tabs with zero items are excluded.
     */
    public function getPendingItems(): array
    {
        $user = $this->user;
        $tabs = [];

        $isDivisionChief = $user->hasRole('DivisionChief');
        $isFADChief = str_contains($user->position ?? '', 'FAD') || $user->hasRole('FAD Chief');
        $isGSUHead = $user->hasRole('GSU Head');
        $isOCD = $user->hasRole('OCD');
        $isHROfficer = $user->hasPermission('hr.leave.approve');
        $isCidChief = $user->hasRole('CID Chief');
        $isAdmin = $user->hasRole('Administrator');

        // Administrator sees all pending items across every module (union of all roles)
        if ($isAdmin) {
            $isDivisionChief = true;
            $isFADChief = true;
            $isGSUHead = true;
            $isOCD = true;
            $isHROfficer = true;
            $isCidChief = true;
        }

        // Pre-compute division IDs for DC queries (used in multiple places)
        $divisionIds = [];
        if ($isDivisionChief && ! $isAdmin) {
            $divisionIds = Division::where('division_chief_id', $user->id)->pluck('id')->toArray();
        } elseif ($isAdmin) {
            // Admin sees all divisions
            $divisionIds = Division::pluck('id')->toArray();
        }

        // ── Division Chief (and Administrator) ───────────────────────────────
        if ($isDivisionChief) {
            // IT Job Requests
            $itQuery = ITJobRequest::with(['user:id,name', 'divisionChief:id,name', 'assignedTo:id,name'])
                ->where('status', 'Pending Division Chief Approval');
            if (! $isAdmin) {
                $itQuery->where('divisionchief_id', $user->id);
            }
            $itItems = $itQuery->latest()->get()
                ->map(fn ($r) => $this->normaliseITJobRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'it_job_requests', 'IT Job Requests', $itItems);

            // Vehicle Requests
            $vrQuery = VehicleRequest::with(['requester:id,name', 'divisionChief:id,name'])
                ->where('status', 'Pending');
            if (! $isAdmin) {
                $vrQuery->where(function ($q) use ($user, $divisionIds) {
                    $q->where('division_chief_id', $user->id);
                    if (! empty($divisionIds)) {
                        $q->orWhereHas('requester', fn ($r) => $r->whereIn('division_id', $divisionIds));
                    }
                });
            }
            $vrItems = $vrQuery->latest()->get()
                ->map(fn ($r) => $this->normaliseVehicleRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'vehicle_requests', 'Vehicle Requests', $vrItems);

            // Facility Requests
            $frQuery = FacilityRequest::with('requester:id,name,division_id')
                ->where('status', 'Pending');
            if (! $isAdmin && ! empty($divisionIds)) {
                $frQuery->whereHas('requester', fn ($q) => $q->whereIn('division_id', $divisionIds));
            }
            if (! $isAdmin && empty($divisionIds)) {
                $frQuery->whereRaw('0 = 1');
            }
            $frItems = $frQuery->latest()->get()
                ->map(fn ($r) => $this->normaliseFacilityRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'facility_requests', 'Facility Requests', $frItems);

            // Work Requests
            $wrQuery = WorkRequest::with(['requester:id,name', 'division:id,name', 'office:id,name'])
                ->where('status', 'Pending');
            if (! $isAdmin) {
                $wrQuery->where(function ($q) use ($user, $divisionIds) {
                    $q->where('division_chief_id', $user->id);
                    if (! empty($divisionIds)) {
                        $q->orWhereHas('requester', fn ($r) => $r->whereIn('division_id', $divisionIds));
                    }
                });
            }
            $wrItems = $wrQuery->latest()->get()
                ->map(fn ($r) => $this->normaliseWorkRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'work_requests', 'Work Requests', $wrItems);

            // Service Requests
            $srQuery = ServiceRequest::with('requester:id,name,division_id')
                ->where('status', 'Pending');
            if (! $isAdmin && ! empty($divisionIds)) {
                $srQuery->whereHas('requester', fn ($q) => $q->whereIn('division_id', $divisionIds));
            }
            if (! $isAdmin && empty($divisionIds)) {
                $srQuery->whereRaw('0 = 1');
            }
            $srItems = $srQuery->latest()->get()
                ->map(fn ($r) => $this->normaliseServiceRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'service_requests', 'Service Requests', $srItems);

            // Messengerial Requests
            $mrQuery = MessengerialRequest::where('status', 'Pending Division Chief Approval');
            if (! $isAdmin) {
                $mrQuery->where('division_chief_id', $user->id);
            }
            $mrItems = $mrQuery->latest()->get()
                ->map(fn ($r) => $this->normaliseMessengerialRequest($r))
                ->values()->all();
            $this->addTab($tabs, 'messengerial_requests', 'Messengerial Requests', $mrItems);

            // Gate Passes (raw DB — no Eloquent model)
            $gpQuery = DB::table('gatepass')
                ->join('users', DB::raw('CAST(users.badge_id AS CHAR)'), '=', DB::raw('CAST(gatepass.badgeNumber AS CHAR)'))
                ->select('gatepass.*', 'users.name as requester_name', 'users.position as requester_position', 'users.division_id')
                ->where('gatepass.status', 'Pending');
            if (! $isAdmin && ! empty($divisionIds)) {
                $gpQuery->whereIn('users.division_id', $divisionIds);
            } elseif (! $isAdmin && empty($divisionIds)) {
                $gpQuery->whereRaw('0 = 1');
            }
            $gpRows = $gpQuery->latest('gatepass.created_at')->get()
                ->map(fn ($r) => $this->normaliseGatePass($r))
                ->values()->all();
            $this->addTab($tabs, 'gate_passes', 'Gate Passes', $gpRows);

            // Leave Applications
            $laQuery = LeaveApplication::with(['user:id,name', 'leaveType:id,name,code', 'hrOfficer:id,name'])
                ->where('status', 'hr_verified');
            if (! $isAdmin) {
                $laQuery->whereHas('user', fn ($q) => $q->whereIn('division_id', $divisionIds));
            }
            $laItems = $laQuery->latest()->get()
                ->map(fn ($r) => $this->normaliseLeaveApplication($r))
                ->values()->all();
            $this->addTab($tabs, 'leave_applications', 'Leave Applications', $laItems);
        }

        // ── FAD Chief ─────────────────────────────────────────────────────────
        if ($isFADChief) {
            $vrFAD = VehicleRequest::with(['requester:id,name', 'divisionChief:id,name'])
                ->where('status', 'Pending FAD Approval')->latest()->get()
                ->map(fn ($r) => $this->normaliseVehicleRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'vehicle_requests', 'Vehicle Requests', $vrFAD);

            $frFAD = FacilityRequest::with('requester:id,name')
                ->where('status', 'Pending FAD Approval')->latest()->get()
                ->map(fn ($r) => $this->normaliseFacilityRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'facility_requests', 'Facility Requests', $frFAD);

            $wrFAD = WorkRequest::with(['requester:id,name', 'division:id,name', 'office:id,name'])
                ->whereIn('status', ['GSU Approved', 'Pending FAD Approval'])->latest()->get()
                ->map(fn ($r) => $this->normaliseWorkRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'work_requests', 'Work Requests', $wrFAD);

            $srFAD = ServiceRequest::with('requester:id,name')
                ->where('status', 'Approved')->latest()->get()
                ->map(fn ($r) => $this->normaliseServiceRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'service_requests', 'Service Requests', $srFAD);
        }

        // ── GSU Head ──────────────────────────────────────────────────────────
        if ($isGSUHead) {
            $vrGSU = VehicleRequest::with(['requester:id,name', 'divisionChief:id,name'])
                ->where('status', 'Approved')->whereNull('driver_id')->latest()->get()
                ->map(fn ($r) => $this->normaliseVehicleRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'vehicle_requests', 'Vehicle Requests', $vrGSU);

            $frGSU = FacilityRequest::with('requester:id,name')
                ->where('status', 'Pending FAD Approval')->latest()->get()
                ->map(fn ($r) => $this->normaliseFacilityRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'facility_requests', 'Facility Requests', $frGSU);

            $wrGSU = WorkRequest::with(['requester:id,name', 'division:id,name', 'office:id,name'])
                ->where('status', 'GSU Approved')->latest()->get()
                ->map(fn ($r) => $this->normaliseWorkRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'work_requests', 'Work Requests', $wrGSU);
        }

        // ── OCD ───────────────────────────────────────────────────────────────
        if ($isOCD) {
            $itOCD = ITJobRequest::with(['user:id,name', 'divisionChief:id,name', 'assignedTo:id,name'])
                ->where('status', 'Pending OCD Approval')->latest()->get()
                ->map(fn ($r) => $this->normaliseITJobRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'it_job_requests', 'IT Job Requests', $itOCD);

            // 'Approved' kept for legacy requests that predate the FAD step
            $vrOCD = VehicleRequest::with(['requester:id,name', 'divisionChief:id,name'])
                ->whereIn('status', ['FAD Approved', 'Approved'])->latest()->get()
                ->map(fn ($r) => $this->normaliseVehicleRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'vehicle_requests', 'Vehicle Requests', $vrOCD);

            $frOCD = FacilityRequest::with('requester:id,name')
                ->where('status', 'Pending OCD Approval')->latest()->get()
                ->map(fn ($r) => $this->normaliseFacilityRequest($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'facility_requests', 'Facility Requests', $frOCD);

            // Messengerial: OCD acts as Division Chief for OCD-division requestors only.
            // Those requests are already covered by the DC block (division_chief_id = $user->id).
            // No separate OCD messengerial queue here.

            $gpOCD = DB::table('gatepass')
                ->leftJoin('users', DB::raw('CAST(users.badge_id AS CHAR)'), '=', DB::raw('CAST(gatepass.badgeNumber AS CHAR)'))
                ->select('gatepass.*', 'users.name as requester_name', 'users.position as requester_position')
                ->where('gatepass.status', 'Division Approved')
                ->latest('gatepass.created_at')->get()
                ->map(fn ($r) => $this->normaliseGatePass($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'gate_passes', 'Gate Passes', $gpOCD);

            $laOCD = LeaveApplication::with(['user:id,name', 'leaveType:id,name,code', 'hrOfficer:id,name', 'divisionChief:id,name'])
                ->where('status', 'forwarded')->latest()->get()
                ->map(fn ($r) => $this->normaliseLeaveApplication($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'leave_applications', 'Leave Applications', $laOCD);

            $pmsOCD = PMS::with('createdBy:id,name')
                ->where('approval_status', 'pending')->latest()->get()
                ->map(fn ($r) => $this->normalisePms($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'pms_schedules', 'PMS Schedules', $pmsOCD);

            $scheduleBatches = ClassScheduleApprovalBatch::with(['academicTerm.schoolYear', 'submitter:id,name'])
                ->where('status', 'pending_ocd')->latest()->get()
                ->map(fn ($batch) => $this->normaliseClassSchedule($batch))->values()->all();
            $this->mergeOrAddTab($tabs, 'class_schedules', 'Class Schedules', $scheduleBatches);
        }

        // ── CID Chief ─────────────────────────────────────────────────────────
        if ($isCidChief) {
            $labApprovals = ComputerLabScheduleApproval::with(['academicTerm.schoolYear', 'submitter:id,name'])
                ->where('status', 'pending_approval')->latest()->get()
                ->map(fn ($approval) => $this->normaliseComputerLabSchedule($approval))->values()->all();
            $this->mergeOrAddTab($tabs, 'computer_lab_schedules', 'Computer Lab Schedules', $labApprovals);
        }

        // ── HR Officer ────────────────────────────────────────────────────────
        if ($isHROfficer) {
            $laHR = LeaveApplication::with(['user:id,name', 'leaveType:id,name,code'])
                ->where('status', 'pending')->latest()->get()
                ->map(fn ($r) => $this->normaliseLeaveApplication($r))->values()->all();
            $this->mergeOrAddTab($tabs, 'leave_applications', 'Leave Applications', $laHR);
        }

        // Return only tabs with items, re-indexed
        return array_values(array_filter($tabs, fn ($t) => $t['count'] > 0));
    }

    // ── Tab helpers ───────────────────────────────────────────────────────────

    private function addTab(array &$tabs, string $type, string $label, array $items): void
    {
        if (! isset($tabs[$type])) {
            $tabs[$type] = ['type' => $type, 'label' => $label, 'count' => 0, 'items' => []];
        }
        $tabs[$type]['items'] = array_merge($tabs[$type]['items'], $items);
        $tabs[$type]['count'] = count($tabs[$type]['items']);
    }

    private function mergeOrAddTab(array &$tabs, string $type, string $label, array $items): void
    {
        $this->addTab($tabs, $type, $label, $items);
    }

    // ── Normalisers ───────────────────────────────────────────────────────────

    private function normaliseITJobRequest(ITJobRequest $r): array
    {
        return [
            'id' => $r->id,
            'type' => 'it_job_requests',
            'reference_no' => $r->itjr_no ?? "#{$r->id}",
            'requester_name' => $r->user?->name ?? '—',
            'filed_at' => $r->created_at?->toISOString(),
            'status' => $r->status,
            'summary' => $r->title ?? $r->category ?? '—',
            'sections' => [
                [
                    'title' => 'Request Information',
                    'fields' => [
                        ['label' => 'ITJR No.',      'value' => $r->itjr_no ?? '—'],
                        ['label' => 'Category',      'value' => $r->category ?? '—'],
                        ['label' => 'Title',         'value' => $r->title ?? '—'],
                        ['label' => 'Event Date',    'value' => $r->event_date?->format('M d, Y') ?? '—'],
                        ['label' => 'Status',        'value' => $r->status],
                        ['label' => 'Filed At',      'value' => $r->created_at?->format('M d, Y h:i A')],
                    ],
                ],
                [
                    'title' => 'Description',
                    'fields' => [
                        ['label' => 'Description', 'value' => $r->description ?? '—', 'full' => true],
                    ],
                ],
                [
                    'title' => 'Assignment',
                    'fields' => [
                        ['label' => 'Assigned To',     'value' => $r->assignedTo?->name ?? '—'],
                        ['label' => 'Division Chief',  'value' => $r->divisionChief?->name ?? '—'],
                        ['label' => 'MIS Assessment',  'value' => $r->mis_assessment ?? '—', 'full' => true],
                        ['label' => 'Recommendation',  'value' => $r->recommendation ?? '—', 'full' => true],
                    ],
                ],
            ],
        ];
    }

    private function normalisePms(PMS $r): array
    {
        return [
            'id' => $r->id,
            'type' => 'pms_schedules',
            'reference_no' => "PMS-{$r->id}",
            'requester_name' => $r->createdBy?->name ?? '—',
            'filed_at' => $r->created_at?->toISOString(),
            'status' => 'Pending OCD Approval',
            'summary' => $r->title ?? '—',
            'sections' => [
                [
                    'title' => 'Schedule Details',
                    'fields' => [
                        ['label' => 'Title',       'value' => $r->title ?? '—'],
                        ['label' => 'Office/Area', 'value' => $r->office_area ?? '—'],
                        ['label' => 'School Year', 'value' => $r->school_year ?? '—'],
                        ['label' => 'Frequency',   'value' => $r->frequency ?? '—'],
                        ['label' => 'Remarks',     'value' => $r->remarks ?? '—', 'full' => true],
                        ['label' => 'Filed At',    'value' => $r->created_at?->format('M d, Y h:i A')],
                    ],
                ],
            ],
        ];
    }

    private function normaliseClassSchedule(ClassScheduleApprovalBatch $batch): array
    {
        $isAmendment = $batch->approval_type === 'swap_amendment';

        return [
            'id' => $batch->id,
            'type' => 'class_schedules',
            'reference_no' => "SCHEDULE-{$batch->id}",
            'requester_name' => $batch->submitter?->name ?? 'CID Chief',
            'filed_at' => $batch->submitted_at?->toISOString(),
            'status' => 'Pending OCD Approval',
            'summary' => ($isAmendment ? 'Schedule Swap Amendment - ' : '').($batch->academicTerm?->full_label ?? 'Class Schedule'),
            'view_url' => route('faculty-loading.schedules.index', ['term_id' => $batch->academic_term_id]),
            'sections' => [[
                'title' => $isAmendment ? 'Schedule Swap Amendment' : 'Schedule Submission',
                'fields' => [
                    ['label' => 'Submission Type', 'value' => $isAmendment ? 'Faculty Schedule Swap Amendment' : 'Complete Class Schedule', 'full' => true],
                    ['label' => 'Academic Term', 'value' => $batch->academicTerm?->full_label ?? '—', 'full' => true],
                    ['label' => 'Submitted By', 'value' => $batch->submitter?->name ?? '—'],
                    ['label' => 'Schedule Entries', 'value' => count($batch->schedule_snapshot ?? [])],
                    ['label' => 'Changed Entries', 'value' => $isAmendment ? count($batch->change_set ?? []) : '—'],
                    ['label' => 'Submitted At', 'value' => $batch->submitted_at?->format('M d, Y h:i A') ?? '—'],
                ],
            ]],
        ];
    }

    private function normaliseComputerLabSchedule(ComputerLabScheduleApproval $approval): array
    {
        return [
            'id' => $approval->id,
            'type' => 'computer_lab_schedules',
            'reference_no' => "COMLAB-{$approval->id}",
            'requester_name' => $approval->submitter?->name ?? 'Science Research Assistant',
            'filed_at' => $approval->submitted_at?->toISOString(),
            'status' => 'Pending CID Chief Approval',
            'summary' => 'Computer Laboratory Schedule - '.($approval->academicTerm?->full_label ?? ''),
            'view_url' => route('computer-labs.index', ['term_id' => $approval->academic_term_id]),
            'sections' => [[
                'title' => 'Computer Laboratory Schedule Submission',
                'fields' => [
                    ['label' => 'Academic Term', 'value' => $approval->academicTerm?->full_label ?? '—', 'full' => true],
                    ['label' => 'Prepared By', 'value' => $approval->submitter?->name ?? '—'],
                    ['label' => 'Booking Entries', 'value' => count($approval->schedule_snapshot ?? [])],
                    ['label' => 'Submitted At', 'value' => $approval->submitted_at?->format('M d, Y h:i A') ?? '—'],
                ],
            ]],
        ];
    }

    private function normaliseVehicleRequest(VehicleRequest $r): array
    {
        $multipleDates = $r->date_needed_multiple
            ? implode(', ', array_map(fn ($d) => Carbon::parse($d)->format('M d, Y'), $r->date_needed_multiple))
            : null;

        return [
            'id' => $r->id,
            'type' => 'vehicle_requests',
            'reference_no' => "#{$r->id}",
            'requester_name' => $r->requester?->name ?? '—',
            'filed_at' => $r->created_at?->toISOString(),
            'status' => $r->status,
            'summary' => $r->purpose ?? '—',
            'sections' => [
                [
                    'title' => 'Trip Details',
                    'fields' => [
                        ['label' => 'Purpose',           'value' => $r->purpose ?? '—', 'full' => true],
                        ['label' => 'Destination',       'value' => $r->destination ?? '—', 'full' => true],
                        ['label' => 'Date Needed',       'value' => $r->date_needed?->format('M d, Y') ?? '—'],
                        ['label' => 'Multiple Dates',    'value' => $multipleDates ?? '—'],
                        ['label' => 'Time of Departure', 'value' => $r->time_of_departure ?? '—'],
                        ['label' => 'ETA',               'value' => $r->eta ?? '—'],
                    ],
                ],
                [
                    'title' => 'Vehicle & Passengers',
                    'fields' => [
                        ['label' => 'Vehicle Type',    'value' => $r->vehicle_type ?? '—'],
                        ['label' => 'No. of Passengers', 'value' => $r->passengers ?? '—'],
                        ['label' => 'Division Chief',  'value' => $r->divisionChief?->name ?? '—'],
                        ['label' => 'Status',          'value' => $r->status],
                        ['label' => 'Filed At',        'value' => $r->created_at?->format('M d, Y h:i A')],
                    ],
                ],
            ],
        ];
    }

    private function normaliseFacilityRequest(FacilityRequest $r): array
    {
        // Resolve venue IDs to facility names
        $venueIds = is_array($r->venue) ? $r->venue : [];
        $venueNames = '—';
        if (! empty($venueIds)) {
            $names = Facility::whereIn('id', $venueIds)->pluck('name')->toArray();
            $venueNames = ! empty($names) ? implode(', ', $names) : implode(', ', array_map('strval', $venueIds));
        }

        // Build equipment list with quantities
        $equipment = [];
        if (is_array($r->equipment)) {
            $qtys = $r->equipment_quantities ?? [];
            foreach ($r->equipment as $i => $eq) {
                $qty = $qtys[$i] ?? null;
                $equipment[] = $qty ? "{$eq} (x{$qty})" : $eq;
            }
        }

        return [
            'id' => $r->id,
            'type' => 'facility_requests',
            'reference_no' => $r->reference_no ?? "#{$r->id}",
            'requester_name' => $r->requester?->name ?? '—',
            'filed_at' => $r->created_at?->toISOString(),
            'status' => $r->status,
            'summary' => $r->activity ?? $r->purpose ?? '—',
            'sections' => [
                [
                    'title' => 'Activity Details',
                    'fields' => [
                        ['label' => 'Activity',     'value' => $r->activity ?? '—',    'full' => true],
                        ['label' => 'Purpose',      'value' => $r->purpose ?? '—',     'full' => true],
                        ['label' => 'Nature',       'value' => $r->nature ?? '—'],
                        ['label' => 'Participants', 'value' => $r->participants ?? '—'],
                        ['label' => 'Male',         'value' => $r->male ?? '—'],
                        ['label' => 'Female',       'value' => $r->female ?? '—'],
                    ],
                ],
                [
                    'title' => 'Schedule & Venue',
                    'fields' => [
                        ['label' => 'Venue',       'value' => $venueNames,                                                                    'full' => true],
                        ['label' => 'Date Start',  'value' => $r->date_start ?? '—'],
                        ['label' => 'Date End',    'value' => $r->date_end ?? '—'],
                        ['label' => 'Time Start',  'value' => $r->time_start ? substr($r->time_start, 0, 5) : '—'],
                        ['label' => 'Time End',    'value' => $r->time_end ? substr($r->time_end, 0, 5) : '—'],
                        ['label' => 'Date Filed',  'value' => $r->date_filed ? Carbon::parse($r->date_filed)->format('M d, Y') : ($r->created_at?->format('M d, Y'))],
                        ['label' => 'Status',      'value' => $r->status],
                    ],
                ],
                [
                    'title' => 'Equipment & Others',
                    'fields' => [
                        ['label' => 'Equipment', 'value' => ! empty($equipment) ? implode(', ', $equipment) : '—', 'full' => true],
                        ['label' => 'Others',    'value' => $r->others ?? '—',  'full' => true],
                        ['label' => 'Remarks',   'value' => $r->remarks ?? '—', 'full' => true],
                    ],
                ],
            ],
        ];
    }

    private function normaliseWorkRequest(WorkRequest $r): array
    {
        return [
            'id' => $r->id,
            'type' => 'work_requests',
            'reference_no' => "#{$r->id}",
            'requester_name' => $r->requester?->name ?? '—',
            'filed_at' => $r->created_at?->toISOString(),
            'status' => $r->status,
            'summary' => $r->issue ?? '—',
            'sections' => [
                [
                    'title' => 'Work Details',
                    'fields' => [
                        ['label' => 'Issue',       'value' => $r->issue ?? '—', 'full' => true],
                        ['label' => 'Category',    'value' => $r->category ?? '—'],
                        ['label' => 'Priority',    'value' => $r->priority ?? '—'],
                        ['label' => 'Description', 'value' => $r->description ?? '—', 'full' => true],
                    ],
                ],
                [
                    'title' => 'Location & Status',
                    'fields' => [
                        ['label' => 'Building/Division', 'value' => $r->division?->name ?? '—'],
                        ['label' => 'Office/Room',       'value' => $r->office?->name ?? '—'],
                        ['label' => 'Status',            'value' => $r->status],
                        ['label' => 'Filed At',          'value' => $r->created_at?->format('M d, Y h:i A')],
                        ['label' => 'Expected Completion', 'value' => $r->expected_completion_date ?? '—'],
                    ],
                ],
            ],
        ];
    }

    private function normaliseServiceRequest(ServiceRequest $r): array
    {
        return [
            'id' => $r->id,
            'type' => 'service_requests',
            'reference_no' => "#{$r->id}",
            'requester_name' => $r->requester?->name ?? '—',
            'filed_at' => $r->created_at?->toISOString(),
            'status' => $r->status,
            'summary' => $r->service_type ?? '—',
            'sections' => [
                [
                    'title' => 'Service Details',
                    'fields' => [
                        ['label' => 'Service Type',    'value' => $r->service_type ?? '—'],
                        ['label' => 'Date Needed',     'value' => $r->date_needed ?? '—'],
                        ['label' => 'Time Needed',     'value' => $r->time_needed ?? '—'],
                        ['label' => 'Copies',          'value' => $r->copies ?? '—'],
                        ['label' => 'Sheets per Set',  'value' => $r->sheets_per_set ?? '—'],
                        ['label' => 'Status',          'value' => $r->status],
                        ['label' => 'Filed At',        'value' => $r->created_at?->format('M d, Y h:i A')],
                    ],
                ],
                [
                    'title' => 'Purpose & Details',
                    'fields' => [
                        ['label' => 'Purpose', 'value' => $r->purposes ?? '—', 'full' => true],
                        ['label' => 'Details', 'value' => $r->details ?? '—', 'full' => true],
                    ],
                ],
            ],
        ];
    }

    private function normaliseMessengerialRequest(MessengerialRequest $r): array
    {
        $deliveryMethods = is_array($r->delivery_methods) ? implode(', ', $r->delivery_methods) : ($r->delivery_methods ?? '—');
        $kinds = is_array($r->messengerial_kinds) ? implode(', ', $r->messengerial_kinds) : ($r->messengerial_kinds ?? '—');

        return [
            'id' => $r->id,
            'type' => 'messengerial_requests',
            'reference_no' => $r->reference_no ?? "#{$r->id}",
            'requester_name' => $r->requestor ?? '—',
            'filed_at' => $r->created_at?->toISOString(),
            'status' => $r->status,
            'summary' => $r->purpose ?? '—',
            'sections' => [
                [
                    'title' => 'Request Details',
                    'fields' => [
                        ['label' => 'Reference No.',    'value' => $r->reference_no ?? '—'],
                        ['label' => 'Purpose',          'value' => $r->purpose ?? '—', 'full' => true],
                        ['label' => 'Destination',      'value' => $r->destination ?? '—', 'full' => true],
                        ['label' => 'Delivery Method',  'value' => $deliveryMethods],
                        ['label' => 'Item Kind',        'value' => $kinds],
                        ['label' => 'Status',           'value' => $r->status],
                        ['label' => 'Filed At',         'value' => $r->created_at?->format('M d, Y h:i A')],
                    ],
                ],
                [
                    'title' => 'Consignee',
                    'fields' => [
                        ['label' => 'Name',    'value' => $r->consignee_name ?? '—'],
                        ['label' => 'Contact', 'value' => $r->consignee_contact ?? '—'],
                        ['label' => 'Email',   'value' => $r->consignee_email ?? '—'],
                    ],
                ],
            ],
        ];
    }

    private function normaliseGatePass(object $r): array
    {
        return [
            'id' => $r->id,
            'type' => 'gate_passes',
            'reference_no' => $r->controlno ?? "#{$r->id}",
            'requester_name' => $r->requester_name ?? '—',
            'filed_at' => $r->created_at ?? null,
            'status' => $r->status,
            'summary' => $r->purpose ?? '—',
            'sections' => [
                [
                    'title' => 'Gate Pass Details',
                    'fields' => [
                        ['label' => 'Control No.',    'value' => $r->controlno ?? '—'],
                        ['label' => 'Purpose',        'value' => $r->purpose ?? '—', 'full' => true],
                        ['label' => 'Destination',    'value' => $r->destination ?? '—', 'full' => true],
                        ['label' => 'Gate Pass Date', 'value' => $r->gatepass_date ?? '—'],
                        ['label' => 'Position',       'value' => $r->requester_position ?? '—'],
                        ['label' => 'Status',         'value' => $r->status],
                    ],
                ],
            ],
        ];
    }

    private function normaliseLeaveApplication(LeaveApplication $r): array
    {
        $dates = is_array($r->dates) ? implode(', ', array_map(fn ($d) => Carbon::parse($d)->format('M d, Y'), $r->dates)) : null;

        return [
            'id' => $r->id,
            'type' => 'leave_applications',
            'reference_no' => $r->control_no ?? "#{$r->id}",
            'requester_name' => $r->user?->name ?? '—',
            'filed_at' => $r->created_at?->toISOString(),
            'status' => $r->status,
            'summary' => ($r->leaveType?->name ?? 'Leave').' — '.($r->days_applied ?? '?').' day(s)',
            'sections' => [
                [
                    'title' => 'Leave Details',
                    'fields' => [
                        ['label' => 'Control No.',   'value' => $r->control_no ?? '—'],
                        ['label' => 'Leave Type',    'value' => $r->leaveType?->name ?? '—'],
                        ['label' => 'Date From',     'value' => $r->date_from?->format('M d, Y') ?? '—'],
                        ['label' => 'Date To',       'value' => $r->date_to?->format('M d, Y') ?? '—'],
                        ['label' => 'Days Applied',  'value' => $r->days_applied ?? '—'],
                        ['label' => 'Specific Dates', 'value' => $dates ?? '—', 'full' => true],
                        ['label' => 'Status',        'value' => $r->status],
                        ['label' => 'Filed At',      'value' => $r->created_at?->format('M d, Y h:i A')],
                    ],
                ],
                [
                    'title' => 'Reason & Details',
                    'fields' => [
                        ['label' => 'Reason',          'value' => $r->reason ?? '—', 'full' => true],
                        ['label' => 'Leave Details',   'value' => $r->leave_details ?? '—', 'full' => true],
                        ['label' => 'Specify',         'value' => $r->leave_details_specify ?? '—', 'full' => true],
                        ['label' => 'Without Pay',     'value' => $r->is_without_pay ? 'Yes' : 'No'],
                    ],
                ],
                [
                    'title' => 'Approval Trail',
                    'fields' => [
                        ['label' => 'HR Officer',         'value' => $r->hrOfficer?->name ?? '—'],
                        ['label' => 'HR Action',          'value' => $r->hr_officer_action ?? '—'],
                        ['label' => 'HR Remarks',         'value' => $r->hr_officer_remarks ?? '—', 'full' => true],
                        ['label' => 'Division Chief',     'value' => $r->divisionChief?->name ?? '—'],
                        ['label' => 'DC Action',          'value' => $r->division_chief_action ?? '—'],
                        ['label' => 'DC Remarks',         'value' => $r->division_chief_remarks ?? '—', 'full' => true],
                    ],
                ],
            ],
        ];
    }
}
