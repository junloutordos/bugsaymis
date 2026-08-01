<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreServiceRecordEntryRequest;
use App\Http\Requests\HR\StoreServiceRecordEvidenceRequest;
use App\Models\HR\ServiceRecordAction;
use App\Models\HR\ServiceRecordEntry;
use App\Models\HR\ServiceRecordEvidence;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HR\ServiceRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ServiceRecordController extends Controller
{
    public function __construct(private ServiceRecordService $service) {}

    public function index(Request $request)
    {
        $this->requirePermission($request, 'hr.service-records.view');

        $employees = User::query()
            ->employees()
            ->with([
                'division:id,division_name',
                'office:id,name',
                'serviceRecordEntries' => fn ($query) => $query->with(['lwopPeriods', 'evidence:id,service_record_entry_id']),
            ])
            ->where('status', '<>', 'inactive')
            ->orderBy('name')
            ->get(['id', 'name', 'employee_no', 'position', 'division_id', 'office_id', 'status'])
            ->map(function (User $employee) {
                $diagnostics = $this->service->diagnostics($employee->serviceRecordEntries);
                $current = $employee->serviceRecordEntries
                    ->whereNull('service_to')->sortByDesc('service_from')->first();

                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'employee_no' => $employee->employee_no,
                    'position' => $employee->position,
                    'division' => $employee->division?->division_name,
                    'office' => $employee->office?->name,
                    'entries_count' => $employee->serviceRecordEntries->count(),
                    'current_appointment' => $current?->position_title,
                    'current_status' => $current?->employment_status,
                    ...$diagnostics,
                ];
            });

        return Inertia::render('HR/ServiceRecords/Index', [
            'employees' => $employees,
        ]);
    }

    public function my(Request $request)
    {
        abort_unless(
            $request->user()->hasPermission('hr.service-records.view-own')
            || $request->user()->hasPermission('hr.service-records.view'),
            403,
        );

        return $this->renderEmployee($request, $request->user());
    }

    public function show(Request $request, User $user)
    {
        $this->authorizeEmployeeView($request, $user);

        return $this->renderEmployee($request, $user);
    }

    public function export(Request $request, User $user)
    {
        $this->requirePermission($request, 'hr.service-records.export');
        $entries = $user->serviceRecordEntries()->with('lwopPeriods')->orderBy('service_from')->get();
        $handle = fopen('php://temp', 'w+');
        fputcsv($handle, [
            'From', 'To', 'Position', 'Employment Status', 'Nature of Appointment',
            'Agency', 'Station/Place', 'Branch/Division', 'Salary', 'Basis', 'SG', 'Step',
            'LWOP Periods', 'Separation Date', 'Separation Cause', 'Creditable', 'Verification Status',
        ]);
        foreach ($entries as $entry) {
            fputcsv($handle, [
                $entry->service_from?->toDateString(),
                $entry->service_to?->toDateString() ?? 'Present',
                $entry->position_title,
                $entry->employment_status,
                $entry->appointment_nature,
                $entry->agency_name,
                $entry->station_place,
                $entry->branch_division,
                $entry->salary_amount,
                $entry->salary_basis,
                $entry->salary_grade,
                $entry->salary_step,
                $entry->lwopPeriods->map(fn ($period) => $period->date_from?->toDateString().'-'.$period->date_to?->toDateString())->join('; '),
                $entry->separation_date?->toDateString(),
                $entry->separation_cause,
                $entry->credited_as_government_service ? 'Yes' : 'No',
                $entry->status,
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="service-record-'.$user->id.'.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function store(StoreServiceRecordEntryRequest $request, User $user)
    {
        $this->service->save($user, $request->validated(), $request->user());

        return back()->with('success', 'Service record entry saved as a draft.');
    }

    public function update(StoreServiceRecordEntryRequest $request, User $user, ServiceRecordEntry $entry)
    {
        $this->service->save($user, $request->validated(), $request->user(), $entry);

        return back()->with('success', 'Service record entry updated and returned to draft review.');
    }

    public function verify(Request $request, ServiceRecordEntry $entry)
    {
        $this->requirePermission($request, 'hr.service-records.verify');
        $this->service->verify($entry, $request->user());

        return back()->with('success', 'Service record entry verified.');
    }

    public function supersede(Request $request, ServiceRecordEntry $entry)
    {
        $this->requirePermission($request, 'hr.service-records.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $entry->update([
            'status' => 'superseded',
            'remarks' => trim(($entry->remarks ? $entry->remarks."\n\n" : '').'Superseded: '.$data['reason']),
            'verified_by' => null,
            'verified_at' => null,
            'updated_by' => $request->user()->id,
        ]);
        AuditLogger::logModelEvent($entry, 'updated');

        return back()->with('success', 'The entry was superseded and retained in the audit history.');
    }

    public function importPds(Request $request, User $user)
    {
        $this->requirePermission($request, 'hr.service-records.manage');
        $count = $this->service->importPds($user, $request->user());

        return back()->with('success', $count
            ? "Imported {$count} PDS work experience entr".($count === 1 ? 'y' : 'ies').' as drafts.'
            : 'No new PDS work experience entries were available to import.');
    }

    public function storeEvidence(
        StoreServiceRecordEvidenceRequest $request,
        ServiceRecordEntry $entry,
    ) {
        $this->service->storeEvidence($entry, $request->validated(), $request->user());

        return back()->with('success', 'Supporting document uploaded securely.');
    }

    public function evidence(Request $request, ServiceRecordEvidence $evidence)
    {
        $evidence->loadMissing('entry');
        $this->authorizeEmployeeView($request, $evidence->entry->user);
        $path = $evidence->getRawOriginal('s3_path');
        abort_unless(Storage::disk('s3')->exists($path), 404);

        return response(Storage::disk('s3')->get($path), 200, [
            'Content-Type' => $evidence->mime_type,
            'Content-Disposition' => 'inline; filename="'.basename($evidence->original_filename).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function destroyEvidence(Request $request, ServiceRecordEvidence $evidence)
    {
        $this->requirePermission($request, 'hr.service-records.manage');
        $path = $evidence->getRawOriginal('s3_path');
        Storage::disk('s3')->delete($path);
        $evidence->delete();

        return back()->with('success', 'Supporting document removed.');
    }

    public function storeAction(Request $request, User $user)
    {
        $this->requirePermission($request, 'hr.service-records.manage');
        $data = $request->validate([
            'service_record_entry_id' => ['nullable', 'integer', Rule::exists('hr_service_record_entries', 'id')->where('user_id', $user->id)],
            'action_type' => ['required', Rule::in(['reassignment', 'detail', 'designation', 'secondment', 'concurrent', 'other'])],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'office_order_no' => ['nullable', 'string', 'max:255'],
            'station_place' => ['nullable', 'string', 'max:255'],
            'branch_division' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);
        $action = ServiceRecordAction::create([
            ...$data,
            'user_id' => $user->id,
            'created_by' => $request->user()->id,
        ]);
        AuditLogger::logModelEvent($action, 'created');

        return back()->with('success', 'HR action annotation added.');
    }

    public function destroyAction(Request $request, ServiceRecordAction $action)
    {
        $this->requirePermission($request, 'hr.service-records.manage');
        AuditLogger::logModelEvent($action, 'deleted');
        $action->delete();

        return back()->with('success', 'HR action annotation removed.');
    }

    private function renderEmployee(Request $request, User $employee)
    {
        $canManage = $request->user()->hasPermission('hr.service-records.manage');

        $employee->load([
            'division:id,division_name',
            'office:id,name',
            'pds.personalInfo',
            'pds.workExperience',
            'serviceRecordEntries' => fn ($query) => $query
                ->when(! $canManage, fn ($entries) => $entries->verified())
                ->with([
                    'lwopPeriods',
                    'evidence.uploader:id,name',
                    'verifier:id,name',
                ]),
            'serviceRecordActions',
            'serviceRecordVersions:id,user_id,version,content_hash,certified_at,created_at',
        ]);

        return Inertia::render('HR/ServiceRecords/Show', [
            'employee' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_no' => $employee->employee_no,
                'position' => $employee->position,
                'status' => $employee->status,
                'division' => $employee->division?->division_name,
                'office' => $employee->office?->name,
                'birth_date' => $employee->pds?->personalInfo?->date_of_birth,
                'birth_place' => $employee->pds?->personalInfo?->place_of_birth,
            ],
            'entries' => $employee->serviceRecordEntries,
            'actions' => $employee->serviceRecordActions,
            'versions' => $employee->serviceRecordVersions,
            'diagnostics' => $this->service->diagnostics($employee->serviceRecordEntries),
            'hasPdsWorkExperience' => (bool) $employee->pds?->workExperience?->isNotEmpty(),
            'permissions' => [
                'manage' => $canManage,
                'verify' => $request->user()->hasPermission('hr.service-records.verify'),
                'certify' => $request->user()->hasPermission('hr.service-records.certify'),
                'export' => $request->user()->hasPermission('hr.service-records.export'),
            ],
        ]);
    }

    private function authorizeEmployeeView(Request $request, User $employee): void
    {
        abort_unless(
            $request->user()->hasPermission('hr.service-records.view')
            || ((int) $request->user()->id === (int) $employee->id
                && $request->user()->hasPermission('hr.service-records.view-own')),
            403,
        );
    }

    private function requirePermission(Request $request, string $permission): void
    {
        abort_unless($request->user()->hasPermission($permission), 403);
    }
}
