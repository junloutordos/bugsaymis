<?php

namespace App\Http\Controllers\Discipline;

use App\Enums\ApprovalStep;
use App\Http\Controllers\Controller;
use App\Models\Discipline\DisciplineCase;
use App\Models\Discipline\DisciplineCaseAttachment;
use App\Models\Discipline\DisciplineIntervention;
use App\Models\Discipline\DisciplineOffense;
use App\Models\DigitalSignature;
use App\Models\User;
use App\Services\Discipline\DisciplineCasePdfService;
use App\Services\Discipline\StudentResolver;
use App\Services\DigitalSignatureService;
use App\Services\NotificationService;
use App\Services\SnapshotService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DisciplineCaseController extends Controller
{
    public function __construct(
        private SnapshotService $snapshots,
        private DisciplineCasePdfService $pdfService,
        private StudentResolver $students,
        private DigitalSignatureService $sigService,
    ) {}

    /* ===================== INDEX ===================== */
    public function index(Request $request)
    {
        $user      = $request->user();
        $canManage = $user->hasPermission('discipline.manage');

        $status = trim($request->query('status', ''));
        $level  = trim($request->query('level', ''));
        $search = trim($request->query('search', ''));

        $cases = DisciplineCase::with(['filer:id,name', 'receiver:id,name', 'offense:id,title,level'])
            // Filers who can only file (not manage) see only their own filed reports
            ->when(! $canManage, fn ($q) => $q->where('filer_id', $user->id))
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($level !== '', fn ($q) => $q->where('offense_level', $level))
            ->when($search, fn ($q) => $q->where(fn ($inner) => $inner
                ->where('case_no', 'like', "%{$search}%")
                ->orWhere('nature_of_offense', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Batch-resolve respondent names (avoid N+1 on the legacy students table)
        $studentMap = $this->studentNameMap($cases->getCollection()->pluck('student_id'));
        $cases->getCollection()->transform(function ($c) use ($studentMap) {
            $c->student_name = $studentMap[$c->student_id] ?? ('Student #' . $c->student_id);
            return $c;
        });

        return Inertia::render('Discipline/Cases/Index', [
            'cases'     => $cases,
            'filters'   => ['status' => $status, 'level' => $level, 'search' => $search],
            'canManage' => $canManage,
            'canFile'   => $user->hasPermission('discipline.file'),
        ]);
    }

    /* ===================== CREATE / STORE ===================== */
    public function create()
    {
        return Inertia::render('Discipline/Cases/Create', [
            'offenses' => DisciplineOffense::where('is_active', true)
                ->orderBy('level')->orderBy('sort_order')->orderBy('title')
                ->get(['id', 'code', 'category', 'level', 'title', 'default_sanction']),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'student_id'         => ['required', 'integer', 'exists:students,id'],
            'offense_id'         => ['nullable', 'integer', 'exists:discipline_offenses,id'],
            'offense_level'      => ['nullable', 'integer', 'in:1,2,3'],
            'nature_of_offense'  => ['nullable', 'string', 'max:1000'],
            'incident_date'      => ['nullable', 'date'],
            'incident_time'      => ['nullable', 'string', 'max:50'],
            'place'              => ['nullable', 'string', 'max:255'],
            'other_witnesses'    => ['nullable', 'string', 'max:2000'],
            'narrative'          => ['required', 'string'],
            'attachments_note'   => ['nullable', 'string', 'max:2000'],
            'interventions_done' => ['nullable', 'string'],
            'is_bullying'        => ['nullable', 'boolean'],
        ]);

        if (empty($validated['offense_id']) && empty($validated['nature_of_offense'])) {
            return back()->withErrors(['nature_of_offense' => 'Select an offense or describe its nature.']);
        }

        // Inherit level from the catalog offense if not explicitly chosen
        if (! empty($validated['offense_id']) && empty($validated['offense_level'])) {
            $validated['offense_level'] = DisciplineOffense::whereKey($validated['offense_id'])->value('level');
        }

        $student = $this->students->resolve((int) $validated['student_id']);

        $case = DB::transaction(function () use ($validated, $user, $student) {
            $case = DisciplineCase::create(array_merge($validated, [
                'is_bullying'             => (bool) ($validated['is_bullying'] ?? false),
                'case_no'                 => $this->generateCaseNo(),
                'filer_id'                => $user->id,
                'filer_position_snapshot' => $user->position,
                'filer_section_snapshot'  => $student['grade_section'],
                'status'                  => 'filed',
                'date_filed'              => now()->toDateString(),
                'time_filed'              => now()->format('g:i A'),
            ]));

            $this->snapshots->recordApproval(
                approvable: $case,
                step:       ApprovalStep::DISCIPLINE_FILED,
                sequence:   1,
                action:     'filed',
                approver:   $user,
                remarks:    'Anecdotal Report filed.',
            );

            return $case;
        });

        $this->notifyDisciplineOfficers($case, 'New Anecdotal Report filed');

        return redirect()->route('discipline.cases.show', $case->id)
            ->with('success', "Anecdotal Report {$case->case_no} filed.");
    }

    /* ===================== SHOW ===================== */
    public function show(Request $request, DisciplineCase $case)
    {
        $user = $request->user();
        $case->load([
            'filer:id,name', 'receiver:id,name', 'resolver:id,name', 'offense',
            'attachments', 'interventions.imposer:id,name', 'approvalSnapshots',
        ]);

        return Inertia::render('Discipline/Cases/Show', [
            'case'      => $case,
            'student'   => $this->students->resolve($case->student_id),
            'offenses'  => DisciplineOffense::where('is_active', true)
                ->orderBy('level')->orderBy('sort_order')
                ->get(['id', 'code', 'category', 'level', 'title', 'default_sanction']),
            'canManage' => $user->hasPermission('discipline.manage'),
            'hasPin'    => ! empty($user->signature_pin),
        ]);
    }

    /* ===================== RECEIVE ===================== */
    public function receive(Request $request, DisciplineCase $case)
    {
        if ($case->status !== 'filed') {
            return back()->with('success', 'Case already received.');
        }

        $case->update([
            'status'      => 'received',
            'received_by' => $request->user()->id,
            'received_at' => now(),
        ]);

        $this->snapshots->recordApproval(
            approvable: $case,
            step:       ApprovalStep::DISCIPLINE_RECEIVED,
            sequence:   2,
            action:     'reviewed',
            approver:   $request->user(),
            remarks:    'Received by Discipline Office.',
        );

        $this->snapshots->captureSignatories($case, [
            ['role_label' => 'Received By', 'user' => $request->user()],
        ]);

        $this->trySign($request, $case, 'received',
            "Anecdotal Report {$case->case_no} — Received");

        $this->notifyFiler($case, 'Your Anecdotal Report has been received');

        return back()->with('success', 'Case received.');
    }

    /* ===================== REVIEW (investigation / classification) ===================== */
    public function review(Request $request, DisciplineCase $case)
    {
        $validated = $request->validate([
            'offense_id'    => ['nullable', 'integer', 'exists:discipline_offenses,id'],
            'offense_level' => ['nullable', 'integer', 'in:1,2,3'],
            'is_bullying'   => ['nullable', 'boolean'],
            'threat_level'  => ['nullable', 'in:low,medium,high'],
            'remarks'       => ['nullable', 'string', 'max:2000'],
        ]);

        $update = collect($validated)->except('remarks')->filter(fn ($v) => $v !== null)->toArray();
        $update['status'] = 'under_review';

        // High-threat bullying → 24h response SLA + parent notification expectation
        if (($validated['threat_level'] ?? null) === 'high' && ! $case->respond_by) {
            $update['respond_by'] = now()->addDay();
        }

        $case->update($update);

        $this->snapshots->recordApproval(
            approvable: $case,
            step:       ApprovalStep::DISCIPLINE_REVIEW,
            sequence:   3,
            action:     'reviewed',
            approver:   $request->user(),
            remarks:    $validated['remarks'] ?? 'Case under review.',
        );

        return back()->with('success', 'Review recorded.');
    }

    /* ===================== RESOLVE / DISMISS ===================== */
    public function resolve(Request $request, DisciplineCase $case)
    {
        $validated = $request->validate([
            'outcome'    => ['required', 'in:resolved,dismissed'],
            'sanction'   => ['nullable', 'string'],
            'resolution' => ['required', 'string'],
            'pin'        => ['nullable', 'string'],
        ]);

        $case->update([
            'status'      => $validated['outcome'],
            'sanction'    => $validated['sanction'] ?? null,
            'resolution'  => $validated['resolution'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        $this->snapshots->recordApproval(
            approvable: $case,
            step:       ApprovalStep::DISCIPLINE_RESOLVED,
            sequence:   4,
            action:     $validated['outcome'] === 'resolved' ? 'approved' : 'rejected',
            approver:   $request->user(),
            remarks:    $validated['resolution'],
        );

        // Capture signatories for the printed Anecdotal Report
        $this->snapshots->captureSignatories($case, [
            ['role_label' => 'Received By', 'user' => $case->received_by ? User::find($case->received_by) : null],
            ['role_label' => 'Resolved By', 'user' => $request->user()],
        ]);

        $this->trySign($request, $case, 'resolved',
            "Anecdotal Report {$case->case_no} — Resolution");

        $label = $validated['outcome'] === 'resolved' ? 'resolved' : 'dismissed';
        $this->notifyFiler($case, "Your Anecdotal Report has been {$label}");

        return back()->with('success', "Case {$label}.");
    }

    /* ===================== CANCEL ===================== */
    public function cancel(Request $request, DisciplineCase $case)
    {
        $user = $request->user();
        if (! $user->hasPermission('discipline.manage') && $case->filer_id !== $user->id) {
            abort(403);
        }
        if (in_array($case->status, ['resolved', 'dismissed'])) {
            return back()->withErrors(['status' => 'A closed case cannot be cancelled.']);
        }

        $case->update(['status' => 'cancelled']);

        return back()->with('success', 'Case cancelled.');
    }

    /* ===================== INTERVENTIONS ===================== */
    public function addIntervention(Request $request, DisciplineCase $case)
    {
        $validated = $request->validate([
            'intervention_type' => ['required', 'string', 'max:255'],
            'description'       => ['nullable', 'string'],
            'start_date'        => ['nullable', 'date'],
            'end_date'          => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        DisciplineIntervention::create(array_merge($validated, [
            'discipline_case_id' => $case->id,
            'imposed_by'         => $request->user()->id,
            'imposed_at'         => now(),
            'status'             => 'active',
        ]));

        return back()->with('success', 'Intervention added.');
    }

    /* ===================== STUDENT SEARCH ===================== */
    public function searchStudents(Request $request)
    {
        return response()->json([
            'students' => $this->students->search(
                (string) $request->query('q', ''),
                (int) $request->query('limit', 10),
            ),
        ]);
    }

    /* ===================== ATTACHMENTS ===================== */
    public function uploadAttachment(Request $request, DisciplineCase $case)
    {
        $validated = $request->validate([
            'file_base64' => ['required', 'string'],
            'file_name'   => ['required', 'string', 'max:255'],
        ]);

        if (! preg_match('/^data:(.*?);base64,(.*)$/s', $validated['file_base64'], $m)) {
            return response()->json(['message' => 'Invalid file payload.'], 422);
        }

        $mime = $m[1] ?: 'application/octet-stream';
        $data = base64_decode($m[2], true);
        if ($data === false) {
            return response()->json(['message' => 'Could not decode file.'], 422);
        }

        $ext = pathinfo($validated['file_name'], PATHINFO_EXTENSION) ?: 'bin';
        $key = "discipline/{$case->id}/" . uniqid('att_') . '.' . $ext;

        Storage::disk('s3')->put($key, $data);

        $attachment = DisciplineCaseAttachment::create([
            'discipline_case_id' => $case->id,
            'file_name'          => $validated['file_name'],
            'file_path'          => $key,
            'mime_type'          => $mime,
            'file_size'          => strlen($data),
            'uploaded_by'        => $request->user()->id,
        ]);

        return response()->json(['message' => 'Attachment uploaded.', 'attachment' => $attachment]);
    }

    public function downloadAttachment(DisciplineCase $case, DisciplineCaseAttachment $attachment)
    {
        abort_unless($attachment->discipline_case_id === $case->id, 404);

        if (! Storage::disk('s3')->exists($attachment->file_path)) {
            abort(404, 'File not found.');
        }

        return response(Storage::disk('s3')->get($attachment->file_path), 200, [
            'Content-Type'        => $attachment->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $attachment->file_name . '"',
        ]);
    }

    public function deleteAttachment(DisciplineCase $case, DisciplineCaseAttachment $attachment)
    {
        abort_unless($attachment->discipline_case_id === $case->id, 404);

        try {
            Storage::disk('s3')->delete($attachment->file_path);
        } catch (\Throwable $e) {
            logger()->warning('Discipline attachment delete failed', ['error' => $e->getMessage()]);
        }
        $attachment->delete();

        return back()->with('success', 'Attachment removed.');
    }

    /* ===================== PDF ===================== */
    public function pdf(DisciplineCase $case)
    {
        return $this->pdfService->stream($case);
    }

    /* ===================== Helpers ===================== */
    private function generateCaseNo(): string
    {
        $prefix = 'DISC-' . now()->format('Y-m');
        $latest = DisciplineCase::where('case_no', 'like', "{$prefix}-%")
            ->lockForUpdate()
            ->selectRaw("MAX(CAST(SUBSTRING_INDEX(case_no, '-', -1) AS UNSIGNED)) as seq")
            ->value('seq');

        return sprintf('%s-%03d', $prefix, ($latest ?? 0) + 1);
    }

    private function studentNameMap($ids): array
    {
        $ids = collect($ids)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('students')->whereIn('id', $ids)
            ->get(['id', 'lastname', 'firstname'])
            ->mapWithKeys(fn ($s) => [
                $s->id => trim(($s->lastname ? $s->lastname . ', ' : '') . ($s->firstname ?? '')) ?: ('Student #' . $s->id),
            ])->all();
    }

    /**
     * Best-effort digital signing for a case stage (mirrors ITJobRequestController).
     * Users with a PIN must supply the correct one; users without a PIN are
     * auto-signed (their electronic signature image renders in the PDF).
     * Never blocks the action if signing fails.
     */
    private function trySign(Request $request, DisciplineCase $case, string $stage, string $title): void
    {
        $signer = $request->user();

        if (! empty($signer->signature_pin)) {
            $pin = $request->input('pin');
            if (! $pin || ! $this->sigService->verifyPin($signer, $pin)) {
                return;
            }
        }

        try {
            $this->sigService->sign(
                signer:        $signer,
                signableType:  DisciplineCase::class,
                signableId:    $case->id,
                documentTitle: $title,
                contentToHash: $case->case_no . $stage . $signer->id,
                metadata:      ['stage' => $stage, 'case_no' => $case->case_no],
            );
        } catch (\Throwable $e) {
            logger()->error('Discipline digital sign failed', [
                'case_id' => $case->id, 'stage' => $stage, 'error' => $e->getMessage(),
            ]);
        }
    }

    private function notifyDisciplineOfficers(DisciplineCase $case, string $message): void
    {
        $officers = User::havingRole('Student Discipline Officer')->get();
        foreach ($officers as $officer) {
            NotificationService::notifyUser(
                $officer, 'Student Discipline', $case->case_no, $message,
                route('discipline.cases.show', $case->id),
            );
        }
    }

    private function notifyFiler(DisciplineCase $case, string $message): void
    {
        $filer = $case->filer ?: User::find($case->filer_id);
        if ($filer) {
            NotificationService::notifyUser(
                $filer, 'Student Discipline', $case->case_no, $message,
                route('discipline.cases.show', $case->id),
            );
        }
    }
}
