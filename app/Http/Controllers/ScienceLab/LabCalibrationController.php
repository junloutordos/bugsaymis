<?php

namespace App\Http\Controllers\ScienceLab;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\ScienceLab\LabCalibrationEvent;
use App\Models\ScienceLab\LabCalibrationSchedule;
use App\Models\ScienceLab\LabEquipment;
use App\Traits\HandlesLabUploads;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LabCalibrationController extends Controller
{
    use HandlesLabUploads;

    public function index(Request $request)
    {
        // Refresh overdue flags on read (§3.5.4).
        LabCalibrationEvent::where('status', 'scheduled')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', Carbon::today())
            ->update(['status' => 'overdue']);

        $sy = SchoolYear::where('is_current', true)->first();

        $schedules = LabCalibrationSchedule::with([
            'equipment:id,description,property_no,room_id,status', 'equipment.room:id,name',
            'events', 'preparedBy:id,name', 'approver:id,name',
        ])->when($sy, fn ($q) => $q->where('school_year_id', $sy->id))->get();

        return Inertia::render('ScienceLab/Calibration', [
            'schedules'  => $schedules,
            'equipment'  => LabEquipment::where('calibration_required', true)->orderBy('description')
                                ->get(['id', 'description', 'property_no', 'calibration_frequency', 'status']),
            'schoolYear' => $sy?->only('id', 'name'),
            'frequencies'=> LabEquipmentController::FREQUENCIES,
            'canApprove' => Auth::user()->hasPermission('lab.calibration.approve'),
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
        $data['status']         = 'draft';

        LabCalibrationSchedule::updateOrCreate(
            ['school_year_id' => $data['school_year_id'], 'lab_equipment_id' => $data['lab_equipment_id']],
            $data
        );

        return back()->with('success', 'Calibration schedule saved.');
    }

    public function submit(LabCalibrationSchedule $schedule)
    {
        abort_unless($schedule->status === 'draft', 422, 'Only draft schedules can be submitted.');
        $schedule->update(['status' => 'submitted']);

        return back()->with('success', 'Calibration schedule submitted for approval.');
    }

    public function approve(LabCalibrationSchedule $schedule)
    {
        abort_unless(Auth::user()->hasPermission('lab.calibration.approve'), 403);
        abort_unless(in_array($schedule->status, ['submitted', 'draft']), 422, 'Schedule cannot be approved.');
        $schedule->update([
            'status'         => 'approved',
            'approved_by_id' => Auth::id(),
            'approved_at'    => now(),
        ]);

        return back()->with('success', 'Calibration schedule approved.');
    }

    public function storeEvent(Request $request)
    {
        $data = $this->validateEvent($request, true);
        $data['lab_equipment_id'] = LabCalibrationSchedule::find($data['lab_calibration_schedule_id'])->lab_equipment_id;
        if ($path = $this->storeLabUpload($request->input('certificate_base64'), 'lab/calibration-certs')) {
            $data['certificate_path'] = $path;
        }
        unset($data['certificate_base64']);

        $event = LabCalibrationEvent::create($data);
        $this->applyResult($event);

        return back()->with('success', 'Calibration record added.');
    }

    public function updateEvent(Request $request, LabCalibrationEvent $event)
    {
        $data = $this->validateEvent($request, false);
        if ($path = $this->storeLabUpload($request->input('certificate_base64'), 'lab/calibration-certs')) {
            $data['certificate_path'] = $path;
        }
        unset($data['certificate_base64'], $data['lab_calibration_schedule_id']);

        $event->update($data);
        $this->applyResult($event->refresh());

        return back()->with('success', 'Calibration record updated.');
    }

    /**
     * Reflect the calibration result on the equipment status:
     * failed instruments are taken out of service (§3.5.7); passed ones return.
     */
    private function applyResult(LabCalibrationEvent $event): void
    {
        if (! $event->lab_equipment_id) {
            return;
        }
        if ($event->result === 'failed') {
            LabEquipment::whereKey($event->lab_equipment_id)->update(['status' => 'out_of_service']);
            $event->update(['status' => 'out_of_service']);
        } elseif ($event->result === 'passed' && $event->actual_date) {
            $event->update(['status' => 'done']);
            LabEquipment::whereKey($event->lab_equipment_id)
                ->where('status', 'for_calibration')->update(['status' => 'active']);
        }
    }

    private function validateEvent(Request $request, bool $creating): array
    {
        return $request->validate([
            'lab_calibration_schedule_id' => [Rule::requiredIf($creating), 'integer', 'exists:lab_calibration_schedules,id'],
            'month'            => ['nullable', 'integer', 'between:1,12'],
            'target_date'      => ['nullable', 'date'],
            'actual_date'      => ['nullable', 'date'],
            'calibrated_by'    => ['nullable', 'string', 'max:150'],
            'is_external'      => ['boolean'],
            'sticker_attached' => ['boolean'],
            'due_date'         => ['nullable', 'date'],
            'result'           => ['required', Rule::in(['passed', 'failed', 'pending'])],
            'remarks'          => ['nullable', 'string', 'max:1000'],
            'certificate_base64' => ['nullable', 'string'],
        ]);
    }
}
