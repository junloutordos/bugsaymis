<?php

namespace App\Http\Controllers;

use App\Http\Traits\SignsDocuments;
use App\Jobs\NotifyAddedIssuanceRecipients;
use App\Jobs\ProcessIssuanceRelease;
use App\Jobs\ResendIssuanceEmails;
use App\Mail\IssuanceReleasedMail;
use App\Models\Division;
use App\Models\FacultyLoading\GradeLevel;
use App\Models\FacultyLoading\SchoolYear;
use App\Models\FacultyLoading\Section;
use App\Models\Issuance;
use App\Models\IssuanceRecipient;
use App\Models\IssuanceType;
use App\Models\Office;
use App\Models\Registrar\StudentEnrollment;
use App\Models\User;
use App\Services\DigitalSignatureService;
use App\Services\IssuanceService;
use App\Services\NotificationService;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class IssuanceController extends Controller
{
    use SignsDocuments;

    public function __construct(
        private IssuanceService        $svc,
        private DigitalSignatureService $sigService,
    ) {}

    // ── Index ─────────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $user    = $request->user();
        $isAdmin = $user->hasPermission('issuances.manage');
        $search  = trim($request->input('search', ''));

        $query = Issuance::whereNull('parent_issuance_id')
            ->with(['creator:id,name,position'])
            ->withCount([
                'recipients',
                'supplements',
                'recipients as acknowledged_count' => fn($q) => $q->whereNotNull('acknowledged_at'),
            ]);

        if (! $isAdmin) {
            // Staff: only see released issuances addressed to them
            $query->where('status', 'released')
                ->whereHas('recipients', fn($q) => $q->where('user_id', $user->id));
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('title', 'LIKE', $like)
                  ->orWhere('control_number', 'LIKE', $like)
                  ->orWhereHas('creator', fn($c) => $c->where('name', 'LIKE', $like))
                  ->orWhere('content_text', 'LIKE', $like)
                  ->orWhereHas('supplements', fn ($s) => $s
                      ->where('title', 'LIKE', $like)
                      ->orWhere('reference_number', 'LIKE', $like)
                      ->orWhere('content_text', 'LIKE', $like));
            });
        }

        $issuances = $query->latest()->get()->map(fn($i) => [
            'id'               => $i->id,
            'type'             => $i->type,
            'type_label'       => $i->type_label,
            'control_number'   => $i->control_number,
            'title'            => $i->title,
            'status'           => $i->status,
            'archived_at'      => $i->archived_at?->toISOString(),
            'supplements_count'=> $i->supplements_count,
            'recipient_type'   => $i->recipient_type,
            'recipients_count' => $i->recipients_count,
            'acknowledged_count' => $i->acknowledged_count,
            'released_at'      => $i->released_at?->toISOString(),
            'created_at'       => $i->created_at?->toISOString(),
            'creator'          => $i->creator?->only('id', 'name', 'position'),
            'my_acknowledged_at' => $isAdmin ? null : $i->recipients
                ->where('user_id', $user->id)->first()?->acknowledged_at?->toISOString(),
        ]);

        return Inertia::render('Issuances/Index', [
            'issuances'  => $issuances,
            'isAdmin'    => $isAdmin,
            'typeLabels' => Issuance::typeLabels(),
            'filters'    => ['search' => $search],
        ]);
    }

    // ── Create ────────────────────────────────────────────────────────────────

    /** @return array{sections: \Illuminate\Support\Collection, gradeLevels: \Illuminate\Support\Collection, students: \Illuminate\Support\Collection} */
    private function recipientTargetingOptions(): array
    {
        $currentSY = SchoolYear::where('is_current', true)->first();

        $sections = $currentSY
            ? Section::where('school_year_id', $currentSY->id)->where('is_active', true)
                ->orderBy('levelid')->orderBy('sectionname')->get(['id', 'sectionname', 'levelid'])
            : collect();

        $gradeLevels = GradeLevel::orderBy('sort_order')->get(['grade', 'label']);

        $students = $currentSY
            ? StudentEnrollment::active()->forSchoolYear($currentSY->id)
                ->with('student:id,lastname,firstname,middlename')
                ->get()
                ->map(fn ($e) => [
                    'id'          => $e->student_id,
                    'full_name'   => $e->student?->full_name,
                    'grade_level' => $e->grade_level,
                    'section_id'  => $e->section_id,
                ])
                ->filter(fn ($s) => $s['full_name'])
                ->values()
            : collect();

        return compact('sections', 'gradeLevels', 'students');
    }

    public function create()
    {
        return Inertia::render('Issuances/Create', array_merge([
            'typeLabels' => Issuance::typeLabels(),
            'offices'    => Office::orderBy('name')->get(['id', 'name']),
            'divisions'  => Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name', 'acronym']),
            'users'      => User::employees()->where('status', '<>', 'inactive')
                ->orderBy('name')->get(['id', 'name', 'office_id', 'position']),
            'hasPin'     => ! empty(auth()->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri(auth()->user()),
        ], $this->recipientTargetingOptions()));
    }

    public function createSupplement(Request $request, Issuance $issuance)
    {
        abort_if(! $request->user()->hasPermission('issuances.manage'), 403);
        abort_if($issuance->isSupplement(), 422, 'Supplemental documents must be attached to a primary issuance.');
        abort_if(! $issuance->isReleased(), 422, 'Only released issuances can receive supplemental documents.');
        abort_if($issuance->isArchived(), 422, 'Unarchive the issuance before adding a supplemental document.');

        return Inertia::render('Issuances/CreateSupplement', [
            'parentIssuance' => [
                'id' => $issuance->id,
                'control_number' => $issuance->control_number,
                'title' => $issuance->title,
                'recipients_count' => $issuance->recipients()->count(),
            ],
            'kindLabels' => Issuance::supplementKindLabels(),
            'hasPin' => ! empty($request->user()->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($request->user()),
        ]);
    }

    public function storeSupplement(Request $request, Issuance $issuance)
    {
        abort_if(! $request->user()->hasPermission('issuances.manage'), 403);
        abort_if($issuance->isSupplement() || ! $issuance->isReleased() || $issuance->isArchived(), 422,
            'Supplemental documents can only be added to an active released issuance.');

        $validated = $request->validate([
            'document_kind' => ['required', Rule::in(array_keys(Issuance::supplementKindLabels()))],
            'title' => 'required|string|max:500',
            'content' => 'nullable|string',
            'scan_base64' => 'nullable|string',
            'scan_filename' => 'nullable|string|max:255',
            'scan_mime' => 'required_with:scan_base64|nullable|string|in:application/pdf,image/jpeg,image/png',
            'should_release' => 'nullable|boolean',
            'pin' => 'nullable|string',
        ]);
        abort_if(empty($validated['content']) && empty($validated['scan_base64']), 422,
            'Enter document content or upload a scanned document.');
        if ($validated['should_release'] ?? false) {
            $this->assertSigningPin($request, $validated);
        }

        $supplement = DB::transaction(function () use ($issuance, $validated, $request) {
            $parent = Issuance::whereKey($issuance->id)->lockForUpdate()->firstOrFail();
            $number = ((int) $parent->supplements()
                ->where('document_kind', $validated['document_kind'])->max('supplement_no')) + 1;
            $kindLabel = Issuance::supplementKindLabels()[$validated['document_kind']];
            $technicalNumber = 'SUP-' . substr(str_replace('-', '', (string) Str::uuid()), 0, 26);
            $referenceNumber = "{$kindLabel} No. {$number} to {$parent->control_number}";

            $attachmentPath = $attachmentFilename = $attachmentMime = null;
            if (! empty($validated['scan_base64'])) {
                $raw = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $validated['scan_base64']), true);
                abort_if($raw === false, 422, 'The uploaded document is not valid base64 data.');
                abort_if(strlen($raw) > 20 * 1024 * 1024, 422, 'The uploaded document may not exceed 20 MB.');
                $ext = match ($validated['scan_mime'] ?? '') {
                    'application/pdf' => 'pdf', 'image/png' => 'png', default => 'jpg',
                };
                $attachmentPath = 'issuances/supplements/' . $technicalNumber . '.' . $ext;
                $attachmentFilename = $validated['scan_filename'] ?? ($technicalNumber . '.' . $ext);
                $attachmentMime = $validated['scan_mime'];
                Storage::disk('s3')->put($attachmentPath, $raw);
            }

            return Issuance::create([
                'parent_issuance_id' => $parent->id,
                'document_kind' => $validated['document_kind'],
                'supplement_no' => $number,
                'reference_number' => $referenceNumber,
                'type' => $parent->type,
                'control_number' => $technicalNumber,
                'series_no' => $parent->series_no,
                'year' => $parent->year,
                'month' => $parent->month,
                'title' => $validated['title'],
                'content' => $validated['content'] ?? null,
                'attachment_path' => $attachmentPath,
                'attachment_filename' => $attachmentFilename,
                'attachment_mime' => $attachmentMime,
                'recipient_type' => $parent->recipient_type,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);
        });

        AuditLogger::logModelEvent($supplement, 'created');

        if ($validated['should_release'] ?? false) {
            $this->doRelease($request, $supplement, $validated);
            return redirect()->route('issuances.show', $supplement)->with('success', "{$supplement->display_number} signed and released.");
        }

        return redirect()->route('issuances.show', $supplement)->with('success', "Draft {$supplement->display_number} saved.");
    }

    // ── Store (save draft) ────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $validated = $request->validate(array_merge([
            'type'               => ['required', Rule::in(array_keys(IssuanceType::activeLabels()))],
            'title'              => 'required|string|max:500',
            'content'            => 'nullable|string',
            'scan_base64'        => 'nullable|string',
            'scan_filename'      => 'nullable|string|max:255',
            'scan_mime'          => 'nullable|string',
            'should_release'     => 'nullable|boolean',
            'pin'                => 'nullable|string',
        ], $this->recipientTargetingRules()));

        $this->assertHasRecipientSelection($validated);

        if ($validated['should_release'] ?? false) {
            $this->assertSigningPin($request, $validated);
        }

        $year  = now()->year;
        $month = now()->month;

        $issuance = DB::transaction(function () use ($validated, $year, $month, $request) {
            [$controlNumber, $seriesNo] = $this->svc->nextControlNumber($validated['type'], $year, $month);

            $attachmentPath = null;
            $attachmentFilename = null;
            $attachmentMime = null;

            if (! empty($validated['scan_base64'])) {
                $raw  = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $validated['scan_base64']));
                $ext  = str_contains($validated['scan_mime'] ?? '', 'pdf') ? 'pdf' : 'jpg';
                $attachmentPath     = 'issuances/scans/' . $controlNumber . '.' . $ext;
                $attachmentFilename = $validated['scan_filename'] ?? ($controlNumber . '.' . $ext);
                $attachmentMime     = $validated['scan_mime'] ?? 'application/pdf';
                Storage::disk('s3')->put($attachmentPath, $raw);
            }

            return Issuance::create([
                'type'               => $validated['type'],
                'control_number'     => $controlNumber,
                'series_no'          => $seriesNo,
                'year'               => $year,
                'month'              => $month,
                'title'              => $validated['title'],
                'content'            => $validated['content'] ?? null,
                'attachment_path'    => $attachmentPath,
                'attachment_filename'=> $attachmentFilename,
                'attachment_mime'    => $attachmentMime,
                'recipient_type'     => $this->svc->summarizeSelectedTypes($validated),
                'status'             => 'draft',
                'created_by'         => $request->user()->id,
            ]);
        });

        // If Sign & Release was requested, do it in the same request
        if ($validated['should_release'] ?? false) {
            $this->doRelease($request, $issuance, $validated);
            return redirect()->route('issuances.show', $issuance->id)
                ->with('success', "{$issuance->control_number} signed and released.");
        }

        return redirect()->route('issuances.show', $issuance->id)
            ->with('success', "Draft {$issuance->control_number} saved.");
    }

    private function doRelease(Request $request, Issuance $issuance, array $data): void
    {
        $this->assertSigningPin($request, $data);

        DB::transaction(function () use ($issuance, $data) {
            $issuance->content_hash = $this->svc->computeHash($issuance);
            $issuance->status       = 'released';
            $issuance->released_at  = now();

            if ($issuance->isSupplement()) {
                $issuance->recipient_type = $issuance->parentIssuance->recipient_type;
                $issuance->save();
                $rows = $issuance->parentIssuance->recipients()->get(['user_id', 'office_id', 'student_id'])->map(fn ($recipient) => [
                    'issuance_id' => $issuance->id,
                    'user_id' => $recipient->user_id,
                    'office_id' => $recipient->office_id,
                    'student_id' => $recipient->student_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();
                if ($rows) IssuanceRecipient::insert($rows);
            } else {
                $issuance->save();
                $this->svc->buildRecipients($issuance, $data);
            }
            $issuance->recipients()->update(['notified_at' => now()]);
        });

        AuditLogger::log([
            'action' => $issuance->isSupplement() ? 'issuance_supplement_released' : 'issuance_released',
            'auditable_type' => Issuance::class,
            'auditable_id' => $issuance->id,
            'new_values' => ['status' => 'released', 'released_at' => $issuance->released_at],
        ]);

        // Sign synchronously (fast — just a DB write)
        try {
            $this->performSign(
                $request,
                Issuance::class,
                $issuance->id,
                'release',
                ($issuance->isSupplement() ? $issuance->display_number : $issuance->type_label) . ": {$issuance->title}",
                $issuance->content_hash,
            );
        } catch (\Throwable $e) {
            logger()->error('Issuance sign failed', ['id' => $issuance->id, 'error' => $e->getMessage()]);
        }

        // Dispatch PDF generation + email/notifications to the queue worker
        // (avoids 504 timeout — queue worker has 600s timeout vs 60s web timeout).
        // Pass primitive ID only — passing the Eloquent model would route through
        // SerializesModels and previously caused "__PHP_Incomplete_Class" errors
        // when an older worker picked up a payload referencing a not-yet-loaded class.
        try {
            ProcessIssuanceRelease::dispatch($issuance->id);
            logger()->info('Issuance release job dispatched', [
                'issuance_id'    => $issuance->id,
                'control_number' => $issuance->control_number,
                'queue'          => config('queue.default'),
            ]);
        } catch (\Throwable $e) {
            logger()->error('Issuance release job dispatch FAILED', [
                'issuance_id' => $issuance->id,
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
            ]);
        }
    }

    private function assertSigningPin(Request $request, array $data): void
    {
        if (! empty($request->user()->signature_pin)) {
            abort_if(empty($data['pin']) || ! $this->sigService->verifyPin($request->user(), $data['pin']), 422,
                'The digital signature PIN is incorrect.');
        }
    }

    private function assertHasRecipientSelection(array $data): void
    {
        $hasAny = ($data['all_staff'] ?? false) || ($data['all_students'] ?? false)
            || ! empty($data['office_ids']) || ! empty($data['division_ids']) || ! empty($data['user_ids'])
            || ! empty($data['section_ids']) || ! empty($data['grade_levels']) || ! empty($data['student_ids']);
        abort_if(! $hasAny, 422, 'Select at least one recipient.');
    }

    private function recipientTargetingRules(): array
    {
        return [
            'all_staff'      => 'nullable|boolean',
            'office_ids'     => 'nullable|array',
            'office_ids.*'   => 'exists:offices,id',
            'division_ids'   => 'nullable|array',
            'division_ids.*' => 'exists:divisions,id',
            'user_ids'       => 'nullable|array',
            'user_ids.*'     => 'exists:users,id',
            'all_students'   => 'nullable|boolean',
            'section_ids'    => 'nullable|array',
            'section_ids.*'  => 'exists:sections,id',
            'grade_levels'   => 'nullable|array',
            'grade_levels.*' => 'exists:grade_levels,grade',
            'student_ids'    => 'nullable|array',
            'student_ids.*'  => 'exists:students,id',
        ];
    }

    // ── Show ──────────────────────────────────────────────────────────────────

    public function show(Request $request, Issuance $issuance)
    {
        $user    = $request->user();
        $isAdmin = $user->hasPermission('issuances.manage');

        // Staff can only view released issuances addressed to them
        if (! $isAdmin) {
            $isRecipient = $issuance->recipients()->where('user_id', $user->id)->exists();
            abort_if(! $isRecipient || $issuance->status !== 'released', 403);
        }

        $issuance->load([
            'creator:id,name,position', 'signature.signer:id,name,position,electronic_signature',
            'parentIssuance:id,control_number,title,status,archived_at',
        ]);

        $supplements = $issuance->isSupplement() ? collect() : $issuance->supplements()
            ->when(! $isAdmin, fn ($q) => $q->where('status', 'released')
                ->whereHas('recipients', fn ($r) => $r->where('user_id', $user->id)))
            ->withCount(['recipients', 'recipients as acknowledged_count' => fn ($q) => $q->whereNotNull('acknowledged_at')])
            ->get()->map(fn ($s) => [
                'id' => $s->id,
                'kind' => $s->document_kind,
                'kind_label' => $s->document_kind_label,
                'reference_number' => $s->display_number,
                'title' => $s->title,
                'status' => $s->status,
                'archived_at' => $s->archived_at?->toISOString(),
                'released_at' => $s->released_at?->toISOString(),
                'recipients_count' => $s->recipients_count,
                'acknowledged_count' => $s->acknowledged_count,
            ]);

        $recipients = $issuance->recipients()
            ->with(['user:id,name,position,office_id', 'office:id,name', 'student:id,lastname,firstname,middlename'])
            ->get()
            ->map(fn($r) => [
                'id'              => $r->id,
                'user'            => $r->user?->only('id', 'name', 'position'),
                'student'         => $r->student ? ['id' => $r->student->id, 'full_name' => $r->student->full_name] : null,
                'office'          => $r->office?->only('id', 'name'),
                'acknowledged_at' => $r->acknowledged_at?->toISOString(),
                'notified_at'     => $r->notified_at?->toISOString(),
                'email_status'    => $r->email_status,
                'emailed_at'      => $r->emailed_at?->toISOString(),
                'email_error'     => $r->email_error,
                'is_me'           => $r->user_id === $user->id,
            ]);

        $sig    = $issuance->signature;
        $sigUri = $sig?->signer ? $this->sigService->getSignatureDataUri($sig->signer) : null;
        $qrSvg  = $issuance->isReleased() ? $this->svc->generateQrSvg($issuance) : null;

        $myRecipient = $isAdmin ? null
            : $issuance->recipients()->where('user_id', $user->id)->first();

        $targetingOptions = $isAdmin ? $this->recipientTargetingOptions() : ['sections' => [], 'gradeLevels' => [], 'students' => []];

        return Inertia::render('Issuances/Show', [
            'divisions'  => $isAdmin ? Division::where('status', 'active')->orderBy('division_name')->get(['id', 'division_name', 'acronym']) : [],
            'offices'    => $isAdmin ? Office::orderBy('name')->get(['id', 'name']) : [],
            'users'      => $isAdmin ? User::employees()->where('status', '<>', 'inactive')
                ->orderBy('name')->get(['id', 'name', 'office_id', 'position']) : [],
            'sections'    => $targetingOptions['sections'],
            'gradeLevels' => $targetingOptions['gradeLevels'],
            'students'    => $targetingOptions['students'],
            'issuance'   => [
                'id'               => $issuance->id,
                'type'             => $issuance->type,
                'type_label'       => $issuance->type_label,
                'control_number'   => $issuance->control_number,
                'display_number'   => $issuance->display_number,
                'document_kind'    => $issuance->document_kind,
                'document_kind_label' => $issuance->document_kind_label,
                'is_supplement'    => $issuance->isSupplement(),
                'parent_issuance'  => $issuance->parentIssuance ? [
                    'id' => $issuance->parentIssuance->id,
                    'control_number' => $issuance->parentIssuance->control_number,
                    'title' => $issuance->parentIssuance->title,
                ] : null,
                'title'            => $issuance->title,
                'content'          => $issuance->content,
                'attachment_path'  => $issuance->attachment_path,
                'has_attachment'   => (bool) $issuance->attachment_path,
                'attachment_filename' => $issuance->attachment_filename,
                'recipient_type'   => $issuance->recipient_type,
                'status'           => $issuance->status,
                'archived_at'      => $issuance->archived_at?->toISOString(),
                'archive_reason'   => $issuance->archive_reason,
                'content_hash'     => $issuance->content_hash,
                'qr_token'         => $issuance->qr_token,
                'qr_svg'           => $qrSvg,
                'released_at'      => $issuance->released_at?->toISOString(),
                'created_at'       => $issuance->created_at?->toISOString(),
                'creator'          => $issuance->creator?->only('id', 'name', 'position'),
                'signature'        => $sig ? [
                    'signer'    => $sig->signer?->only('id', 'name', 'position'),
                    'signed_at' => $sig->signed_at?->toISOString(),
                    'sig_uri'   => $sigUri,
                ] : null,
            ],
            'recipients'  => $recipients,
            'supplements' => $supplements,
            'isAdmin'     => $isAdmin,
            'hasPin'      => ! empty($user->signature_pin),
            'signatureUri'=> $isAdmin ? $this->sigService->getSignatureDataUri($user) : null,
            'myAcknowledgedAt' => $myRecipient?->acknowledged_at?->toISOString(),
            'verifyUrl'   => $issuance->isReleased()
                ? route('issuances.verify', $issuance->qr_token)
                : null,
        ]);
    }

    // ── Release (sign + publish) ──────────────────────────────────────────────

    public function release(Request $request, Issuance $issuance)
    {
        abort_if(! $request->user()->hasPermission('issuances.manage'), 403);
        abort_if(! $issuance->isEditable(), 422, 'Only draft issuances can be released.');
        abort_if($issuance->isArchived(), 422, 'Restore this draft from the archive before releasing it.');
        if ($issuance->isSupplement()) {
            abort_if(! $issuance->parentIssuance?->isReleased() || $issuance->parentIssuance?->isArchived(), 422,
                'The parent issuance must be active and released.');
        }

        $validated = $request->validate(array_merge([
            'pin' => 'nullable|string',
        ], $this->recipientTargetingRules()));

        if (! $issuance->isSupplement()) {
            $this->assertHasRecipientSelection($validated);
        }

        $this->doRelease($request, $issuance, $validated);

        return back()->with('success', "{$issuance->display_number} released to recipients.");
    }

    public function archive(Request $request, Issuance $issuance)
    {
        abort_if(! $request->user()->hasPermission('issuances.manage'), 403);
        abort_if($issuance->isArchived(), 422, 'This record is already archived.');
        $validated = $request->validate(['reason' => 'nullable|string|max:500']);
        $issuance->update([
            'archived_at' => now(),
            'archived_by' => $request->user()->id,
            'archive_reason' => $validated['reason'] ?? null,
        ]);
        AuditLogger::log([
            'action' => 'issuance_archived',
            'auditable_type' => Issuance::class,
            'auditable_id' => $issuance->id,
            'new_values' => ['archived_at' => $issuance->archived_at, 'archive_reason' => $issuance->archive_reason],
        ]);

        return back()->with('success', "{$issuance->display_number} archived.");
    }

    public function unarchive(Request $request, Issuance $issuance)
    {
        abort_if(! $request->user()->hasPermission('issuances.manage'), 403);
        abort_if(! $issuance->isArchived(), 422, 'This record is not archived.');
        $issuance->update(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null]);
        AuditLogger::log([
            'action' => 'issuance_unarchived',
            'auditable_type' => Issuance::class,
            'auditable_id' => $issuance->id,
            'old_values' => ['archived' => true],
            'new_values' => ['archived' => false],
        ]);

        return back()->with('success', "{$issuance->display_number} restored from the archive.");
    }

    public function destroy(Request $request, Issuance $issuance)
    {
        abort_if(! $request->user()->hasPermission('issuances.manage'), 403);
        abort_if(! $issuance->isEditable(), 422, 'Released issuances and supplemental documents cannot be deleted. Archive them instead.');
        abort_if($issuance->supplements()->exists(), 422, 'Delete the draft supplemental documents before deleting this issuance.');

        $attachmentPath = $issuance->attachment_path;
        $pdfPath = 'issuances/' . $issuance->control_number . '.pdf';
        AuditLogger::logModelEvent($issuance, 'deleted');
        $issuance->delete();
        if ($attachmentPath) Storage::disk('s3')->delete($attachmentPath);
        Storage::disk('s3')->delete($pdfPath);

        return redirect()->route('issuances.index')->with('success', 'Draft deleted permanently.');
    }

    // ── Acknowledge ───────────────────────────────────────────────────────────

    public function acknowledge(Request $request, Issuance $issuance)
    {
        $uid       = $request->user()->id;
        $recipient = $issuance->recipients()->where('user_id', $uid)->firstOrFail();

        abort_if($recipient->acknowledged_at, 422, 'Already acknowledged.');

        $recipient->update(['acknowledged_at' => now()]);

        // Notify OCD that someone acknowledged
        $creator = $issuance->creator;
        if ($creator) {
            try {
                NotificationService::notifyUser(
                    $creator, 'Issuance', $issuance->control_number,
                    "Acknowledged by {$request->user()->name}",
                    route('issuances.show', $issuance->id),
                );
            } catch (\Throwable) {}
        }

        return back()->with('success', 'Receipt acknowledged.');
    }

    // ── Download PDF ──────────────────────────────────────────────────────────

    public function downloadPdf(Request $request, Issuance $issuance)
    {
        $user    = $request->user();
        $isAdmin = $user->hasPermission('issuances.manage');

        if (! $isAdmin) {
            $isRecipient = $issuance->recipients()->where('user_id', $user->id)->exists();
            abort_if(! $isRecipient || $issuance->status !== 'released', 403);
        }

        // Regenerate on demand if not on S3 yet
        $pdfPath = 'issuances/' . $issuance->control_number . '.pdf';
        if (! Storage::disk('s3')->exists($pdfPath)) {
            $pdfPath = $this->svc->generatePdf($issuance);
        }

        $url = Storage::disk('s3')->temporaryUrl($pdfPath, now()->addMinutes(5));
        return redirect($url);
    }

    // ── View scan (S3 proxy) ──────────────────────────────────────────────────

    public function viewScan(Request $request, Issuance $issuance)
    {
        $user    = $request->user();
        $isAdmin = $user->hasPermission('issuances.manage');

        if (! $isAdmin) {
            $isRecipient = $issuance->recipients()->where('user_id', $user->id)->exists();
            abort_if(! $isRecipient || $issuance->status !== 'released', 403);
        }

        abort_if(! $issuance->attachment_path, 404);

        $content = Storage::disk('s3')->get($issuance->attachment_path);
        return response($content, 200, [
            'Content-Type'        => $issuance->attachment_mime ?? 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $issuance->attachment_filename . '"',
            'Cache-Control'       => 'private, max-age=300',
        ]);
    }

    // ── Add recipients (post-release) ───────────────────────────────────────

    /** Add more recipients to an already-released issuance; new recipients are emailed + bell-notified. */
    public function addRecipients(Request $request, Issuance $issuance)
    {
        abort_if(! $issuance->isReleased(), 422, 'Only released issuances can receive additional recipients.');
        abort_if($issuance->isArchived(), 422, 'Restore this issuance from the archive before adding recipients.');

        $validated = $request->validate($this->recipientTargetingRules());
        $this->assertHasRecipientSelection($validated);

        $newRecipientIds = $this->svc->addRecipients($issuance, $validated);

        if (empty($newRecipientIds)) {
            return back()->with('success', 'No new recipients — everyone selected already has this issuance.');
        }

        AuditLogger::log([
            'action'         => 'issuance_recipients_added',
            'auditable_type' => Issuance::class,
            'auditable_id'   => $issuance->id,
            'new_values'     => ['added_count' => count($newRecipientIds)],
        ]);

        NotifyAddedIssuanceRecipients::dispatch($issuance->id, $newRecipientIds);

        return back()->with('success', count($newRecipientIds) . ' new recipient(s) added and notified.');
    }

    // ── Resend recipient email ───────────────────────────────────────────────

    /** Re-send one recipient's issuance email (e.g. after fixing their email on file). */
    public function resendRecipientEmail(Issuance $issuance, IssuanceRecipient $recipient)
    {
        abort_if($recipient->issuance_id !== $issuance->id, 404);
        abort_if(! $issuance->isReleased(), 422, 'Issuance has not been released yet.');

        $u = $recipient->user;
        if (! $u || empty($u->email)) {
            $recipient->update([
                'email_status' => 'skipped',
                'email_error'  => 'No email on file for this recipient.',
            ]);

            return back()->withErrors(['email' => 'This recipient has no email on file.']);
        }

        try {
            Mail::to($u->email)->send(new IssuanceReleasedMail($issuance, $u->name));
            $recipient->update(['email_status' => 'sent', 'emailed_at' => now(), 'email_error' => null]);

            return back()->with('success', "Issuance re-sent to {$u->email}.");
        } catch (\Throwable $e) {
            $recipient->update(['email_status' => 'failed', 'email_error' => $e->getMessage()]);
            logger()->warning('Issuance resend failed', [
                'issuance_id'  => $issuance->id,
                'recipient_id' => $recipient->id,
                'error'        => $e->getMessage(),
            ]);

            return back()->withErrors(['email' => 'Sending failed: ' . $e->getMessage()]);
        }
    }

    /** Re-send the issuance email to multiple recipients at once (queued — can be the full recipient list). */
    public function resendBulkEmails(Request $request, Issuance $issuance)
    {
        abort_if(! $issuance->isReleased(), 422, 'Issuance has not been released yet.');

        $request->validate([
            'recipient_ids'   => 'required|array|min:1',
            'recipient_ids.*' => 'integer',
        ]);

        $ids = $issuance->recipients()->whereIn('id', $request->recipient_ids)->pluck('id')->all();
        abort_if(empty($ids), 422, 'No valid recipients selected.');

        ResendIssuanceEmails::dispatch($issuance->id, $ids);

        return back()->with('success', 'Resending to ' . count($ids) . ' recipient(s) — statuses will update shortly.');
    }
}
