<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\ScienceLab\LabEquipment;
use App\Models\ScienceLab\LabPmRecord;
use App\Models\ScienceLab\LabPmSchedule;
use App\Models\ScienceLab\LabRepairTicket;
use App\Models\ScienceLab\LabServiceLog;
use App\Services\ScienceLab\LabControlNumberService;
use App\Traits\HandlesLabUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LabMaintenanceController extends Controller
{
    use HandlesLabUploads;

    public const PM_STATUSES = ['as_scheduled', 'not_as_scheduled', 'not_performed', 'none'];
    public const TICKET_STATUSES = ['open', 'in_repair', 'resolved', 'filed', 'cancelled'];

    public function __construct(private LabControlNumberService $controlNumbers) {}

    public function index(Request $request)
    {
        $sy = SchoolYear::where('is_current', true)->first();

        $schedules = LabPmSchedule::with(['equipment:id,description,property_no,room_id', 'equipment.room:id,name', 'records'])
            ->when($sy, fn ($q) => $q->where('school_year_id', $sy->id))
            ->get()->map(fn ($s) => [
                'id'          => $s->id,
                'equipment'   => $s->equipment?->description,
                'property_no' => $s->equipment?->property_no,
                'location'    => $s->equipment?->room?->name,
                'frequency'   => $s->frequency,
                'records'     => $s->records->mapWithKeys(fn ($r) => [$r->month => $r->status]),
            ]);

        $tickets = LabRepairTicket::with(['equipment:id,description,property_no', 'reporter:id,name', 'assignee:id,name'])
            ->orderByDesc('date_reported')->get();

        return Inertia::render('ScienceLab/Maintenance', [
            'schedules'  => $schedules,
            'tickets'    => $tickets,
            'equipment'  => LabEquipment::orderBy('description')->get(['id', 'description', 'property_no', 'pm_required', 'pm_frequency', 'status']),
            'schoolYear' => $sy?->only('id', 'name'),
            'pmStatuses' => self::PM_STATUSES,
            'ticketStatuses' => self::TICKET_STATUSES,
            'frequencies' => LabEquipmentController::FREQUENCIES,
        ]);
    }

    public function storeSchedule(Request $request)
    {
        $data = $request->validate([
            'lab_equipment_id' => ['required', 'integer', 'exists:lab_equipment,id'],
            'frequency'        => ['required', Rule::in(LabEquipmentController::FREQUENCIES)],
            'remarks'          => ['nullable', 'string', 'max:1000'],
        ]);
        $data['school_year_id'] = SchoolYear::where('is_current', true)->value('id');
        $data['prepared_by_id'] = Auth::id();

        LabPmSchedule::updateOrCreate(
            ['school_year_id' => $data['school_year_id'], 'lab_equipment_id' => $data['lab_equipment_id']],
            $data
        );

        return back()->with('success', 'Preventive maintenance schedule saved.');
    }

    public function updateRecord(Request $request)
    {
        $data = $request->validate([
            'lab_pm_schedule_id' => ['required', 'integer', 'exists:lab_pm_schedules,id'],
            'month'              => ['required', 'integer', 'between:1,12'],
            'status'             => ['required', Rule::in(self::PM_STATUSES)],
            'remarks'            => ['nullable', 'string', 'max:255'],
        ]);

        LabPmRecord::updateOrCreate(
            ['lab_pm_schedule_id' => $data['lab_pm_schedule_id'], 'month' => $data['month']],
            [
                'status'       => $data['status'],
                'remarks'      => $data['remarks'] ?? null,
                'performed_on' => $data['status'] === 'as_scheduled' || $data['status'] === 'not_as_scheduled'
                    ? now()->toDateString() : null,
            ]
        );

        return back()->with('success', 'Maintenance status updated.');
    }

    public function storeTicket(Request $request)
    {
        $data = $request->validate([
            'lab_equipment_id' => ['nullable', 'integer', 'exists:lab_equipment,id'],
            'problem'          => ['required', 'string', 'max:2000'],
            'priority'         => ['required', Rule::in(['low', 'medium', 'high'])],
            'immediate_action' => ['nullable', 'string', 'max:2000'],
            'external_provider'=> ['nullable', 'string', 'max:150'],
        ]);
        $data['control_no']     = $this->controlNumbers->next(LabControlNumberService::REPAIR, 'lab_repair_tickets');
        $data['reported_by_id'] = Auth::id();
        $data['date_reported']  = now()->toDateString();
        $data['status']         = 'open';

        $ticket = LabRepairTicket::create($data);
        if (! empty($data['lab_equipment_id'])) {
            LabEquipment::whereKey($data['lab_equipment_id'])->update(['status' => 'under_repair']);
        }

        return back()->with('success', "Repair ticket {$ticket->control_no} opened.");
    }

    public function updateTicket(Request $request, LabRepairTicket $ticket)
    {
        $data = $request->validate([
            'status'           => ['required', Rule::in(self::TICKET_STATUSES)],
            'immediate_action' => ['nullable', 'string', 'max:2000'],
            'external_provider'=> ['nullable', 'string', 'max:150'],
            'signage_placed'   => ['boolean'],
            'assigned_to_id'   => ['nullable', 'integer', 'exists:users,id'],
            'date_completed'   => ['nullable', 'date'],
            'remarks'          => ['nullable', 'string', 'max:2000'],
            'service_report_base64' => ['nullable', 'string'],
        ]);

        if ($path = $this->storeLabUpload($request->input('service_report_base64'), 'lab/service-reports')) {
            $data['service_report_path'] = $path;
        }
        unset($data['service_report_base64']);

        if (in_array($data['status'], ['resolved', 'filed']) && empty($data['date_completed'])) {
            $data['date_completed'] = now()->toDateString();
        }
        $data['reviewed_by_id'] = Auth::id();
        $ticket->update($data);

        // Return equipment to service once repair is resolved/filed.
        if (in_array($data['status'], ['resolved', 'filed']) && $ticket->lab_equipment_id) {
            LabEquipment::whereKey($ticket->lab_equipment_id)->update(['status' => 'active']);
        }

        return back()->with('success', 'Repair ticket updated.');
    }

    public function storeServiceLog(Request $request)
    {
        $data = $request->validate([
            'lab_equipment_id'  => ['required', 'integer', 'exists:lab_equipment,id'],
            'repair_ticket_id'  => ['nullable', 'integer', 'exists:lab_repair_tickets,id'],
            'log_date'          => ['required', 'date'],
            'particulars'       => ['required', 'string', 'max:2000'],
            'type'              => ['required', Rule::in(['preventive', 'corrective'])],
            'remarks_reference' => ['nullable', 'string', 'max:255'],
            'cost_of_materials' => ['nullable', 'numeric', 'min:0'],
            'serviced_by'       => ['nullable', 'string', 'max:150'],
        ]);
        $data['inputted_by_id'] = Auth::id();

        LabServiceLog::create($data);

        return back()->with('success', 'Service log entry added.');
    }
}
