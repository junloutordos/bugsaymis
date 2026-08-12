<?php

namespace App\Http\Controllers\ALP;

use App\Http\Controllers\Controller;
use App\Models\ALP\AlpActivity;
use App\Models\ALP\AlpAttendance;
use App\Models\ALP\AlpDocument;
use App\Models\ALP\AlpFinancialEntry;
use App\Models\ALP\AlpMembership;
use App\Models\ALP\AlpOfficer;
use App\Models\ALP\AlpProgramCycle;
use App\Models\ALP\AlpReport;
use App\Models\ALP\AlpSession;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\Registrar\StudentAcademicStanding;
use App\Models\Registrar\StudentEnrollment;
use App\Services\ALP\AlpAccessService;
use App\Services\ALP\AlpAmsIntegrationService;
use App\Services\ALP\AlpComplianceService;
use App\Services\ALP\AlpFileService;
use App\Services\ALP\AlpProgramSyncService;
use App\Services\ALP\AlpRosterService;
use App\Services\ALP\AlpWorkflowService;
use App\Services\NotificationService;
use App\Services\SnapshotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AlpController extends Controller
{
    public function __construct(
        private AlpAccessService $access,
        private AlpComplianceService $compliance,
        private AlpProgramSyncService $sync,
        private AlpWorkflowService $workflow,
        private AlpAmsIntegrationService $ams,
        private AlpFileService $files,
        private AlpRosterService $roster,
        private SnapshotService $snapshots,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $schoolYearId = $request->integer('school_year_id') ?: $schoolYears->firstWhere('is_current', true)?->id;
        $elevated = $user->isSuperAdmin() || $user->hasAnyPermission(['alp.manage', 'alp.coordinate', 'alp.registrar-certify', 'alp.approve', 'alp.reports', 'alp.audit']);

        $cycles = AlpProgramCycle::query()
            ->with(['program:id,code,name,status', 'schoolYear:id,name,is_current', 'adviser:id,name'])
            ->withCount([
                'memberships as active_members_count' => fn ($q) => $q->where('status', 'active'),
                'officers', 'activities',
            ])
            ->when($schoolYearId, fn ($q) => $q->where('school_year_id', $schoolYearId))
            ->when(! $elevated, fn ($q) => $q->where(fn ($scope) => $scope->where('adviser_id', $user->id)->orWhere('coordinator_id', $user->id)))
            ->orderBy('status')
            ->get();

        $requiredStudentCount = StudentEnrollment::query()
            ->where('school_year_id', $schoolYearId)
            ->where('status', 'enrolled')
            ->whereBetween('grade_level', [7, 10])
            ->count();
        $assignedRequiredCount = AlpMembership::query()
            ->where('school_year_id', $schoolYearId)
            ->where('status', 'active')
            ->whereHas('enrollment', fn ($q) => $q->whereBetween('grade_level', [7, 10]))
            ->count();

        return Inertia::render('CID/ALP/Index', [
            'cycles' => $cycles,
            'schoolYears' => $schoolYears,
            'selectedSchoolYearId' => $schoolYearId,
            'stats' => [
                'programs' => $cycles->count(),
                'accredited' => $cycles->where('status', 'accredited')->count(),
                'members' => $cycles->sum('active_members_count'),
                'unassignedRequired' => max(0, $requiredStudentCount - $assignedRequiredCount),
            ],
            'canSync' => $user->isSuperAdmin() || $user->hasAnyPermission(['alp.manage', 'alp.coordinate']),
        ]);
    }

    public function membersIndex(Request $request)
    {
        $schoolYears = SchoolYear::orderByDesc('name')->get(['id', 'name', 'is_current']);
        $schoolYearId = $request->integer('school_year_id') ?: $schoolYears->firstWhere('is_current', true)?->id;

        return Inertia::render('CID/ALP/Members', [
            'members' => $this->roster->activeMembers($schoolYearId, $request->user()),
            'schoolYears' => $schoolYears,
            'selectedSchoolYearId' => $schoolYearId,
        ]);
    }

    public function sync(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasAnyPermission(['alp.manage', 'alp.coordinate']), 403);
        $cycles = $this->sync->syncCurrentSchoolYear($request->user());

        return back()->with('success', "Synchronized {$cycles->count()} ALP program(s) from current ALA adviser designations.");
    }

    public function show(Request $request, AlpProgramCycle $cycle)
    {
        $this->access->authorizeView($request->user(), $cycle);
        $cycle->load([
            'program.designation:id,code,name', 'schoolYear:id,name,is_current', 'adviser:id,name,email', 'coordinator:id,name,email',
            'memberships' => fn ($q) => $q->with(['student:id,firstname,lastname,middlename,sex,pisaysystemID', 'enrollment.section:id,sectionname,levelid'])->orderBy('id'),
            'officers.membership.student:id,firstname,lastname,middlename,sex,pisaysystemID', 'officers.certifier:id,name',
            'documents', 'activities.amsActivity:id,title', 'sessions.attendance', 'financialEntries', 'reports',
            'activityLogs.actor:id,name', 'approvalSnapshots',
        ]);
        $cycle->memberships->each(fn ($membership) => $membership->student?->append('full_name'));
        $cycle->officers->each(fn ($officer) => $officer->membership?->student?->append('full_name'));

        $balances = $this->financialSummary($cycle);
        $attendanceSummary = $this->attendanceSummary($cycle);

        return Inertia::render('CID/ALP/Show', [
            'cycle' => $cycle,
            'checklist' => $this->compliance->checklist($cycle),
            'yearEndDeficiencies' => $this->compliance->yearEndDeficiencies($cycle),
            'financialSummary' => $balances,
            'attendanceSummary' => $attendanceSummary,
            'abilities' => [
                'manage' => $this->access->canManage($request->user(), $cycle),
                'coordinate' => $request->user()->isSuperAdmin() || $request->user()->hasAnyPermission(['alp.manage', 'alp.coordinate']) || (int) $cycle->coordinator_id === (int) $request->user()->id,
                'certify' => $request->user()->isSuperAdmin() || $request->user()->hasPermission('alp.registrar-certify'),
                'recommend' => $request->user()->isSuperAdmin() || $request->user()->hasRole('CID Chief'),
                'approve' => $request->user()->isSuperAdmin() || $request->user()->hasRole('OCD'),
                'activityApprove' => $request->user()->isSuperAdmin() || $request->user()->hasPermission('alp.approve'),
                'reports' => $request->user()->isSuperAdmin() || $request->user()->hasAnyPermission(['alp.reports', 'alp.coordinate', 'alp.manage']),
            ],
            'hasPin' => ! empty($request->user()->signature_pin),
            'integrationLinks' => [
                ['label' => 'Activity Management', 'href' => route('ams.activities.index')],
                ['label' => 'IPCR Accomplishments', 'href' => route('my-accomplishments.index')],
                ['label' => 'Facility Requests', 'href' => route('facility-requests.index')],
                ['label' => 'Vehicle Requests', 'href' => route('vehicle-requests.index')],
                ['label' => 'IT Job Requests', 'href' => route('jobrequests.index')],
            ],
        ]);
    }

    public function updateProgram(Request $request, AlpProgramCycle $cycle)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        $data = $request->validate([
            'name' => 'required|string|max:255', 'nature' => 'nullable|string|max:5000',
            'mission' => 'nullable|string|max:5000', 'objectives' => 'nullable|array|max:20',
            'objectives.*' => 'required|string|max:1000',
        ]);
        $cycle->program->update($data);
        $this->workflow->log($cycle, $request->user(), 'program_updated', $cycle->program);

        return back()->with('success', 'ALP profile updated.');
    }

    public function searchStudents(Request $request, AlpProgramCycle $cycle)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        $search = trim((string) $request->query('search'));
        if (mb_strlen($search) < 2) {
            return response()->json([]);
        }

        $enrollments = StudentEnrollment::query()
            ->where('school_year_id', $cycle->school_year_id)->where('status', 'enrolled')
            ->whereHas('student', fn ($q) => $q->where(function ($s) use ($search) {
                $s->where('firstname', 'like', "%{$search}%")
                    ->orWhere('lastname', 'like', "%{$search}%")
                    ->orWhere('pisaysystemID', 'like', "%{$search}%");
            }))
            ->with(['student:id,firstname,lastname,middlename,sex,pisaysystemID', 'section:id,sectionname,levelid'])
            ->limit(20)->get();

        $assigned = AlpMembership::where('school_year_id', $cycle->school_year_id)
            ->whereIn('student_id', $enrollments->pluck('student_id'))
            ->with('cycle.program:id,name')->get()->keyBy('student_id');

        return response()->json($enrollments->map(fn ($e) => [
            'student_id' => $e->student_id, 'enrollment_id' => $e->id, 'name' => $e->student?->full_name,
            'sex' => $e->student?->sex, 'grade_level' => $e->grade_level, 'section' => $e->section?->sectionname,
            'assigned_program' => $assigned->get($e->student_id)?->cycle?->program?->name,
        ])->values());
    }

    public function storeMembers(Request $request, AlpProgramCycle $cycle)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_if(! in_array($cycle->status, ['draft', 'returned'], true), 422, 'Membership is locked after submission.');
        $data = $request->validate(['student_ids' => 'required|array|min:1', 'student_ids.*' => 'integer|distinct']);
        $enrollments = StudentEnrollment::query()->where('school_year_id', $cycle->school_year_id)
            ->where('status', 'enrolled')->whereIn('student_id', $data['student_ids'])->get()->keyBy('student_id');
        if ($enrollments->count() !== count($data['student_ids'])) {
            throw ValidationException::withMessages(['student_ids' => 'Every selected scholar must have an active enrollment for this school year.']);
        }

        DB::transaction(function () use ($cycle, $request, $data, $enrollments) {
            foreach ($data['student_ids'] as $studentId) {
                $existing = AlpMembership::where('school_year_id', $cycle->school_year_id)->where('student_id', $studentId)->first();
                if ($existing && $existing->alp_program_cycle_id !== $cycle->id) {
                    throw ValidationException::withMessages(['student_ids' => "Student #{$studentId} is already assigned to another ALP."]);
                }
                AlpMembership::firstOrCreate(
                    ['school_year_id' => $cycle->school_year_id, 'student_id' => $studentId],
                    ['alp_program_cycle_id' => $cycle->id, 'student_enrollment_id' => $enrollments[$studentId]->id, 'joined_at' => now()->toDateString(), 'created_by' => $request->user()->id]
                );
            }
        });
        $this->workflow->log($cycle, $request->user(), 'members_added', $cycle, ['count' => count($data['student_ids'])]);

        return back()->with('success', 'ALP members added.');
    }

    public function destroyMember(Request $request, AlpProgramCycle $cycle, AlpMembership $membership)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_unless($membership->alp_program_cycle_id === $cycle->id, 404);
        abort_if(! in_array($cycle->status, ['draft', 'returned'], true), 422, 'Membership is locked after submission.');
        $membership->delete();

        return back()->with('success', 'Member removed.');
    }

    public function updateConsent(Request $request, AlpProgramCycle $cycle, AlpMembership $membership)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_unless($membership->alp_program_cycle_id === $cycle->id, 404);
        $data = $request->validate([
            'consent_status' => 'required|in:pending,received,declined',
            'file_base64' => 'nullable|string|max:15000000',
        ]);
        if (! empty($data['file_base64'])) {
            $this->files->delete($membership->consent_file_id);
            $data['consent_file_id'] = $this->files->uploadDataUri("{$cycle->school_year_id}/{$cycle->id}/consents/{$membership->student_id}", $data['file_base64']);
        }
        unset($data['file_base64']);
        $membership->update($data);
        $this->workflow->log($cycle, $request->user(), 'consent_updated', $membership, ['status' => $membership->consent_status]);

        return back()->with('success', 'Parent consent record updated.');
    }

    public function updateAccountability(Request $request, AlpProgramCycle $cycle, AlpMembership $membership)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_unless($membership->alp_program_cycle_id === $cycle->id, 404);
        $data = $request->validate(['accountability' => 'nullable|string|max:3000']);
        $membership->update($data);
        $this->workflow->log($cycle, $request->user(), 'member_accountability_updated', $membership);

        return back()->with('success', blank($membership->accountability) ? 'Member accountability cleared.' : 'Member accountability recorded.');
    }

    public function storeOfficer(Request $request, AlpProgramCycle $cycle)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_if(! in_array($cycle->status, ['draft', 'returned'], true), 422, 'Officers are locked after submission.');
        $data = $request->validate([
            'membership_id' => ['required', 'integer', Rule::exists('alp_memberships', 'id')->where('alp_program_cycle_id', $cycle->id)],
            'position' => 'required|string|max:100', 'sort_order' => 'nullable|integer|min:0|max:100',
        ]);
        AlpOfficer::updateOrCreate(
            ['alp_program_cycle_id' => $cycle->id, 'alp_membership_id' => $data['membership_id']],
            ['position' => $data['position'], 'sort_order' => $data['sort_order'] ?? 0, 'registrar_status' => 'pending', 'certified_by' => null, 'certified_at' => null]
        );

        return back()->with('success', 'Officer saved and queued for Registrar certification.');
    }

    public function destroyOfficer(Request $request, AlpProgramCycle $cycle, AlpOfficer $officer)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_unless($officer->alp_program_cycle_id === $cycle->id, 404);
        abort_if(! in_array($cycle->status, ['draft', 'returned'], true), 422, 'Officers are locked after submission.');
        $officer->delete();

        return back()->with('success', 'Officer removed.');
    }

    public function certifyOfficer(Request $request, AlpProgramCycle $cycle, AlpOfficer $officer)
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasPermission('alp.registrar-certify'), 403);
        abort_unless($officer->alp_program_cycle_id === $cycle->id, 404);
        $data = $request->validate(['status' => 'required|in:certified,returned', 'remarks' => 'nullable|string|max:2000', 'pin' => 'nullable|string']);
        $this->workflow->verifyPinWhenConfigured($request->user(), $data['pin'] ?? null);
        $membership = $officer->membership;
        $standing = StudentAcademicStanding::where('student_id', $membership->student_id)->where('school_year_id', $cycle->school_year_id)->first();
        $snapshot = $standing ? $standing->only(['gwa', 'subject_count', 'failed_subject_count', 'honors', 'standing', 'processed_at']) : ['standing' => 'No processed academic standing record'];
        $officer->update([
            'registrar_status' => $data['status'], 'academic_standing_snapshot' => $snapshot,
            'certified_by' => $data['status'] === 'certified' ? $request->user()->id : null,
            'certified_at' => $data['status'] === 'certified' ? now() : null,
            'certification_remarks' => $data['remarks'] ?? null,
        ]);
        $this->workflow->log($cycle, $request->user(), 'officer_'.$data['status'], $officer, ['remarks' => $data['remarks'] ?? null]);

        return back()->with('success', 'Officer certification updated.');
    }

    public function saveDocument(Request $request, AlpProgramCycle $cycle, AlpDocument $document)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_unless($document->alp_program_cycle_id === $cycle->id, 404);
        abort_if(! in_array($cycle->status, ['draft', 'returned'], true), 422, 'Documents are locked while the package is under review.');
        $data = $request->validate(['content' => 'required|array', 'status' => 'required|in:draft,submitted']);
        $document->update([
            ...$data, 'prepared_by' => $request->user()->id,
            'submitted_at' => $data['status'] === 'submitted' ? now() : null,
        ]);
        $this->workflow->log($cycle, $request->user(), 'document_'.$data['status'], $document);

        return back()->with('success', 'Controlled ALP document saved.');
    }

    public function transition(Request $request, AlpProgramCycle $cycle)
    {
        $data = $request->validate(['action' => 'required|in:submit,review,recommend,approve,return,close', 'remarks' => 'nullable|string|max:3000', 'pin' => 'nullable|string']);
        $action = $data['action'];
        if (in_array($action, ['submit', 'close'], true)) {
            $this->access->authorizeManage($request->user(), $cycle);
        }
        if ($action === 'review') {
            abort_unless($request->user()->isSuperAdmin() || $request->user()->hasAnyPermission(['alp.manage', 'alp.coordinate']) || (int) $cycle->coordinator_id === (int) $request->user()->id, 403);
        }
        if ($action === 'recommend') {
            abort_unless($request->user()->isSuperAdmin() || $request->user()->hasRole('CID Chief'), 403);
        }
        if ($action === 'approve') {
            abort_unless($request->user()->isSuperAdmin() || $request->user()->hasRole('OCD'), 403);
        }
        if ($action === 'return') {
            abort_unless($request->user()->isSuperAdmin() || $request->user()->hasAnyPermission(['alp.coordinate', 'alp.approve']) || (int) $cycle->coordinator_id === (int) $request->user()->id, 403);
        }
        $this->workflow->transition($cycle, $action, $request->user(), $data['remarks'] ?? null, $data['pin'] ?? null);

        return back()->with('success', 'ALP accreditation workflow updated.');
    }

    public function storeActivity(Request $request, AlpProgramCycle $cycle)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        $data = $this->validateActivity($request);
        $activity = $cycle->activities()->create([...$data, 'prepared_by' => $request->user()->id]);
        $this->workflow->log($cycle, $request->user(), 'activity_created', $activity);

        return back()->with('success', 'ALP activity plan created.');
    }

    public function updateActivity(Request $request, AlpProgramCycle $cycle, AlpActivity $activity)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_unless($activity->alp_program_cycle_id === $cycle->id, 404);
        abort_if(! in_array($activity->status, ['draft', 'returned'], true), 422, 'Only draft or returned activities can be edited.');
        $activity->update($this->validateActivity($request));
        $this->workflow->log($cycle, $request->user(), 'activity_updated', $activity);

        return back()->with('success', 'ALP activity plan updated.');
    }

    public function activityAction(Request $request, AlpProgramCycle $cycle, AlpActivity $activity)
    {
        abort_unless($activity->alp_program_cycle_id === $cycle->id, 404);
        $data = $request->validate(['action' => 'required|in:submit,approve,return,complete', 'remarks' => 'nullable|string|max:3000', 'pin' => 'nullable|string']);
        $action = $data['action'];
        if (in_array($action, ['submit', 'complete'], true)) {
            $this->access->authorizeManage($request->user(), $cycle);
        }
        if (in_array($action, ['approve', 'return'], true)) {
            abort_unless($request->user()->isSuperAdmin() || $request->user()->hasPermission('alp.approve'), 403);
        }

        if ($action === 'submit') {
            abort_unless(in_array($activity->status, ['draft', 'returned'], true), 422);
            abort_unless($cycle->status === 'accredited', 422, 'The ALP must be accredited before an activity can be submitted.');
            abort_if(! $activity->risk_level || empty($activity->risk_data), 422, 'Complete the risk assessment before submitting the activity.');
            if ($activity->consent_required && $cycle->memberships()->where('status', 'active')->where('consent_status', '<>', 'received')->exists()) {
                throw ValidationException::withMessages(['consent' => 'Parent consent is incomplete for one or more active members.']);
            }
            $activity->update(['status' => 'submitted']);
        } elseif ($action === 'approve') {
            abort_unless($activity->status === 'submitted', 422);
            $this->workflow->verifyPinWhenConfigured($request->user(), $data['pin'] ?? null);
            $activity->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
            $this->snapshots->recordApproval($activity, 'acid_student_affairs', 1, 'approved', $request->user(), $data['remarks'] ?? '');
            $this->ams->syncApprovedActivity($activity);
        } elseif ($action === 'return') {
            abort_unless($activity->status === 'submitted', 422);
            abort_if(blank($data['remarks'] ?? null), 422, 'Return remarks are required.');
            $activity->update(['status' => 'returned']);
        } else {
            abort_unless($activity->status === 'approved', 422);
            $activity->update(['status' => 'completed', 'completed_at' => now(), 'completion_highlights' => $data['remarks'] ?? $activity->completion_highlights]);
        }
        $this->workflow->log($cycle, $request->user(), 'activity_'.$action, $activity, ['remarks' => $data['remarks'] ?? null]);
        if ($cycle->adviser && $cycle->adviser_id !== $request->user()->id) {
            NotificationService::notifyUser($cycle->adviser, 'ALP Activity', $cycle->program->code, str($activity->status)->title()->toString(), route('alp.cycles.show', $cycle), $data['remarks'] ?? null);
        }

        return back()->with('success', 'ALP activity workflow updated.');
    }

    public function storeSession(Request $request, AlpProgramCycle $cycle)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        $data = $request->validate([
            'activity_id' => ['nullable', Rule::exists('alp_activities', 'id')->where('alp_program_cycle_id', $cycle->id)],
            'session_date' => 'required|date', 'start_time' => 'nullable|date_format:H:i', 'end_time' => 'nullable|date_format:H:i|after_or_equal:start_time',
            'topic' => 'nullable|string|max:255', 'venue' => 'nullable|string|max:255',
        ]);
        $session = $cycle->sessions()->create([...$data, 'alp_activity_id' => $data['activity_id'] ?? null, 'created_by' => $request->user()->id]);
        $rows = $cycle->memberships()->where('status', 'active')->get()->map(fn ($membership) => [
            'alp_session_id' => $session->id, 'alp_membership_id' => $membership->id, 'status' => 'present',
            'recorded_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ])->all();
        AlpAttendance::insert($rows);

        return back()->with('success', 'Session created with the active member roster.');
    }

    public function saveAttendance(Request $request, AlpProgramCycle $cycle, AlpSession $session)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_unless($session->alp_program_cycle_id === $cycle->id, 404);
        $data = $request->validate([
            'records' => 'required|array', 'records.*.membership_id' => 'required|integer',
            'records.*.status' => 'required|in:present,absent,tardy,cutting,excused', 'records.*.remarks' => 'nullable|string|max:1000',
        ]);
        DB::transaction(function () use ($data, $session, $cycle, $request) {
            foreach ($data['records'] as $record) {
                abort_unless($cycle->memberships()->whereKey($record['membership_id'])->exists(), 422);
                AlpAttendance::updateOrCreate(
                    ['alp_session_id' => $session->id, 'alp_membership_id' => $record['membership_id']],
                    ['status' => $record['status'], 'remarks' => $record['remarks'] ?? null, 'recorded_by' => $request->user()->id]
                );
            }
            $session->update(['status' => 'closed']);
        });

        return back()->with('success', 'Attendance saved.');
    }

    public function storeFinance(Request $request, AlpProgramCycle $cycle)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        $data = $request->validate([
            'activity_id' => ['nullable', Rule::exists('alp_activities', 'id')->where('alp_program_cycle_id', $cycle->id)],
            'transaction_date' => 'required|date', 'entry_type' => 'required|in:opening_balance,income,expense,turnover',
            'category' => 'nullable|string|max:80', 'description' => 'required|string|max:255', 'amount' => 'required|numeric|min:0|max:999999999.99',
            'source' => 'nullable|string|max:255', 'receipt_base64' => 'nullable|string|max:15000000',
        ]);
        if (! empty($data['receipt_base64'])) {
            $data['receipt_file_id'] = $this->files->uploadDataUri("{$cycle->school_year_id}/{$cycle->id}/finance", $data['receipt_base64']);
        }
        unset($data['receipt_base64']);
        $entry = $cycle->financialEntries()->create([...$data, 'alp_activity_id' => $data['activity_id'] ?? null, 'recorded_by' => $request->user()->id]);
        $this->workflow->log($cycle, $request->user(), 'financial_entry_created', $entry, ['amount' => $entry->amount, 'type' => $entry->entry_type]);

        return back()->with('success', 'Financial entry recorded.');
    }

    public function destroyFinance(Request $request, AlpProgramCycle $cycle, AlpFinancialEntry $entry)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        abort_unless($entry->alp_program_cycle_id === $cycle->id, 404);
        abort_if($entry->status !== 'posted', 422, 'Finalized entries cannot be deleted.');
        $this->files->delete($entry->receipt_file_id);
        $entry->delete();

        return back()->with('success', 'Financial entry removed.');
    }

    public function storeReport(Request $request, AlpProgramCycle $cycle)
    {
        $this->access->authorizeManage($request->user(), $cycle);
        $data = $request->validate([
            'report_type' => 'required|in:accomplishment,attendance_summary,financial,coordinator,adviser_evaluation',
            'period' => 'required|string|max:40', 'data' => 'required|array', 'status' => 'required|in:draft,submitted',
        ]);
        $report = AlpReport::updateOrCreate(
            ['alp_program_cycle_id' => $cycle->id, 'report_type' => $data['report_type'], 'period' => $data['period']],
            [
                'form_code' => match ($data['report_type']) {
                    'financial' => 'PSHS-00-F-DSA-34',
                    'accomplishment' => 'PSHS-00-F-DSA-35',
                    'attendance_summary' => 'PSHS-00-F-DSA-36',
                    'coordinator' => 'PSHS-00-F-DSA-37',
                    default => null,
                },
                'version_no' => 2, 'revision_no' => 0,
                'data' => $data['data'], 'status' => $data['status'], 'prepared_by' => $request->user()->id,
                'submitted_at' => $data['status'] === 'submitted' ? now() : null,
            ]
        );
        $this->workflow->log($cycle, $request->user(), 'report_'.$data['status'], $report);

        return back()->with('success', 'ALP report saved.');
    }

    public function approveReport(Request $request, AlpProgramCycle $cycle, AlpReport $report)
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasAnyPermission(['alp.coordinate', 'alp.approve']), 403);
        abort_unless($report->alp_program_cycle_id === $cycle->id && $report->status === 'submitted', 422);
        $data = $request->validate(['pin' => 'nullable|string', 'remarks' => 'nullable|string|max:2000']);
        $this->workflow->verifyPinWhenConfigured($request->user(), $data['pin'] ?? null);
        $report->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'approved_at' => now()]);
        $this->snapshots->recordApproval($report, 'alp_report_review', 1, 'approved', $request->user(), $data['remarks'] ?? '');

        return back()->with('success', 'ALP report approved.');
    }

    public function file(Request $request, AlpProgramCycle $cycle, string $fileId)
    {
        $this->access->authorizeView($request->user(), $cycle);
        abort_unless(preg_match('/^[A-Za-z0-9_.=-]+$/', $fileId), 404);

        return $this->files->stream($fileId);
    }

    private function validateActivity(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:255', 'activity_type' => 'required|in:regular,major,community_service',
            'start_date' => 'required|date', 'start_time' => 'nullable|date_format:H:i', 'end_date' => 'required|date|after_or_equal:start_date',
            'end_time' => 'nullable|date_format:H:i', 'learning_outcomes' => 'required|string|max:5000',
            'target_participants' => 'required|string|max:2000', 'venue' => 'required|string|max:255', 'is_off_campus' => 'nullable|boolean',
            'budget_amount' => 'nullable|numeric|min:0|max:999999999.99', 'resources_needed' => 'nullable|string|max:5000',
            'risk_level' => 'required|in:low,medium,high', 'risk_data' => 'required|array', 'consent_required' => 'nullable|boolean',
        ]);
    }

    private function financialSummary(AlpProgramCycle $cycle): array
    {
        $entries = $cycle->financialEntries;
        $opening = (float) $entries->where('entry_type', 'opening_balance')->sum('amount');
        $income = (float) $entries->where('entry_type', 'income')->sum('amount');
        $expenses = (float) $entries->where('entry_type', 'expense')->sum('amount');
        $turnover = (float) $entries->where('entry_type', 'turnover')->sum('amount');

        return compact('opening', 'income', 'expenses', 'turnover') + ['available' => $opening + $income - $expenses - $turnover];
    }

    private function attendanceSummary(AlpProgramCycle $cycle): array
    {
        $sessionCount = $cycle->sessions->count();

        return $cycle->memberships->map(function ($membership) use ($cycle, $sessionCount) {
            $records = $cycle->sessions->flatMap->attendance->where('alp_membership_id', $membership->id);

            return [
                'membership_id' => $membership->id, 'name' => $membership->student?->full_name,
                'sessions' => $sessionCount, 'present' => $records->where('status', 'present')->count(),
                'absent' => $records->where('status', 'absent')->count(), 'tardy' => $records->where('status', 'tardy')->count(),
                'cutting' => $records->where('status', 'cutting')->count(), 'excused' => $records->where('status', 'excused')->count(),
            ];
        })->values()->all();
    }
}
