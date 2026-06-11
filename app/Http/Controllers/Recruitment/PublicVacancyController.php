<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\Application;
use App\Models\Campus;
use App\Models\JobVacancy;
use App\Models\RecruitmentType;
use App\Services\GoogleDriveService;
use App\Services\Recruitment\ApplicationWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PublicVacancyController extends Controller
{
    // Document types required per job posting (labels shown to applicant)
    const REQUIRED_DOCUMENTS = [
        'application_letter'  => 'Application Letter',
        'pds'                 => 'Personal Data Sheet (CSC Form 212 revised 2025)',
        'work_experience'     => 'Work Experience Sheet',
        'transcript'          => 'Transcript of Records',
        'eligibility'         => 'Copy of Eligibility',
        'ipcr'                => 'Latest IPCR Rating (optional)',
    ];

    public function __construct(
        private ApplicationWorkflowService $workflow,
        private GoogleDriveService $drive,
    ) {}

    // ── Public listing ─────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = JobVacancy::with(['jobItem.office', 'jobItem.recruitmentType'])
            ->where('status', 'open')
            ->where('closing_date', '>=', now()->toDateString());

        if ($typeId = $request->integer('type_id')) {
            $query->whereHas('jobItem', fn ($q) => $q->where('recruitment_type_id', $typeId));
        }

        if ($search = $request->string('search')->trim()) {
            $query->whereHas('jobItem', fn ($q) => $q->where('position_title', 'like', "%{$search}%"));
        }

        $vacancies = $query->orderByDesc('posting_date')->orderByDesc('id')->paginate(12)->withQueryString();
        $types     = RecruitmentType::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Recruitment/Public/Vacancies', [
            'vacancies'           => $vacancies,
            'types'               => $types,
            'filters'             => $request->only(['type_id', 'search']),
            'required_documents'  => self::REQUIRED_DOCUMENTS,
            'campus'              => Campus::first(['name', 'code', 'logo']),
        ]);
    }

    // ── Single vacancy detail ──────────────────────────────────────────────────

    public function show(JobVacancy $vacancy)
    {
        abort_if($vacancy->status !== 'open', 404);
        $vacancy->load([
            'jobItem.office',
            'jobItem.recruitmentType.evaluationCriteria',
            'jobItem.requirements',
        ]);

        return Inertia::render('Recruitment/Public/VacancyShow', [
            'vacancy'            => $vacancy,
            'required_documents' => self::REQUIRED_DOCUMENTS,
            'campus'             => Campus::first(['name', 'code', 'logo']),
        ]);
    }

    // ── Submit application with Drive document uploads ─────────────────────────

    public function apply(Request $request, JobVacancy $vacancy)
    {
        if ($vacancy->status !== 'open') {
            return response()->json([
                'errors' => ['_general' => ['This vacancy is no longer accepting applications.']],
            ], 422);
        }

        $validated = $request->validate([
            'first_name'          => 'required|string|max:100',
            'middle_name'         => 'nullable|string|max:100',
            'last_name'           => 'required|string|max:100',
            'suffix'              => 'nullable|string|max:20',
            'birthdate'           => 'required|date|before:today',
            'email'               => 'required|email|max:191',
            'contact_number'      => 'required|string|max:30',
            'address'             => 'required|string|max:500',
            'civil_status'        => 'required|in:single,married,widowed,separated',
            'eligibility'         => 'nullable|string|max:255',
            'prc_license_no'      => 'nullable|string|max:50',
            'school'              => 'nullable|string|max:255',
            'course'              => 'nullable|string|max:255',
            'year_graduated'      => 'nullable|integer|min:1950|max:' . now()->year,
            'is_internal'         => 'boolean',
            'remarks'             => 'nullable|string|max:1000',
            // Consolidated application documents — sent as a base64 data URI (Cloudflare blocks multipart uploads)
            'documents_base64'    => 'required|string',
            'documents_filename'  => 'required|string|max:255',
            'documents_mime'      => 'nullable|string|max:100',
        ], [
            'documents_base64.required' => 'Please attach your consolidated application documents.',
        ]);

        // Prevent duplicate applications to the same vacancy
        $existingApplicant = Applicant::where('email', $validated['email'])->first();
        if ($existingApplicant && Application::where('applicant_id', $existingApplicant->id)
                ->where('job_vacancy_id', $vacancy->id)->exists()) {
            return response()->json([
                'errors' => ['_general' => ['You have already applied for this position. Use the application tracker to check your status.']],
            ], 422);
        }

        $position = $vacancy->jobItem->position_title ?? 'Position';
        $lastName  = strtoupper($validated['last_name']);
        $firstName = strtoupper($validated['first_name']);

        // Upload files to Google Drive in a subfolder named: "LASTNAME, FIRSTNAME - Position"
        $driveFolder = "{$lastName}, {$firstName} - {$position}";

        // Validate, decode, and upload the consolidated documents file
        $allowedExts = ['pdf', 'doc', 'docx'];
        $filename    = $validated['documents_filename'];
        $mime        = $validated['documents_mime'] ?? 'application/octet-stream';
        $ext         = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($ext, $allowedExts, true)) {
            return response()->json([
                'errors' => ['documents_base64' => ['File must be one of: ' . implode(', ', $allowedExts) . '.']],
            ], 422);
        }

        $raw = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $validated['documents_base64']));

        if (strlen($raw) > 10 * 1024 * 1024) {
            return response()->json([
                'errors' => ['documents_base64' => ['File exceeds the 10MB limit.']],
            ], 422);
        }

        $fileName = "[{$driveFolder}] Application Documents.{$ext}";

        try {
            $result = $this->drive->uploadRaw($raw, $fileName, $mime);
            $uploadedDoc = [
                'drive_file_id' => $result['file_id'],
                'drive_url'     => $result['link'],
                'original_name' => $filename,
                'file_size'     => strlen($raw),
                'mime_type'     => $mime,
            ];
        } catch (\Throwable $e) {
            Log::error('Drive upload failed for application_documents: ' . $e->getMessage());
            return response()->json([
                'errors' => ['documents_base64' => ['Failed to upload your documents. Please try again.']],
            ], 422);
        }

        // Create applicant + application + document record in one transaction
        try {
            $application = DB::transaction(function () use ($validated, $vacancy, $uploadedDoc) {
                $applicant = Applicant::updateOrCreate(
                    ['email' => $validated['email']],
                    array_merge(
                        array_intersect_key($validated, array_flip([
                            'first_name','middle_name','last_name','suffix',
                            'birthdate','email','contact_number','address',
                            'civil_status','eligibility','prc_license_no',
                            'school','course','year_graduated',
                        ])),
                        ['status' => 'active', 'source' => 'online']
                    )
                );

                // Store the consolidated documents record
                ApplicantDocument::updateOrCreate(
                    ['applicant_id' => $applicant->id, 'document_type' => 'application_documents'],
                    array_merge($uploadedDoc, ['file_path' => $uploadedDoc['drive_url'], 'status' => 'pending'])
                );

                return $this->workflow->apply($applicant, $vacancy, [
                    'is_internal' => $validated['is_internal'] ?? false,
                    'remarks'     => $validated['remarks'] ?? null,
                ]);
            });
        } catch (\RuntimeException $e) {
            return response()->json(['errors' => ['_general' => [$e->getMessage()]]], 422);
        }

        return back()->with('success',
            "Application submitted successfully! Your reference number is #{$application->id}. " .
            "Your documents have been uploaded. You will be notified by email of any updates."
        );
    }

    // ── Public application status tracker ─────────────────────────────────────

    public function trackForm()
    {
        return Inertia::render('Recruitment/Public/TrackApplication', [
            'result' => null,
        ]);
    }

    public function track(Request $request)
    {
        $request->validate([
            'email'          => 'required|email',
            'application_id' => 'required|integer|min:1',
        ]);

        $application = Application::with([
            'applicant',
            'jobVacancy.jobItem.office',
            'jobVacancy.jobItem.recruitmentType',
            'placement',
        ])
        ->where('id', $request->application_id)
        ->whereHas('applicant', fn ($q) => $q->where('email', $request->email))
        ->first();

        if (! $application) {
            return back()->withErrors(['application_id' => 'No application found with that email and reference number.']);
        }

        // Build timeline — all stages with their status relative to current
        $stagesFlow = [
            'submitted'  => 'Application Submitted',
            'screening'  => 'Document Screening',
            'exam'       => 'Written Examination',
            'interview'  => 'Panel Interview',
            'ranking'    => 'Ranking & Deliberation',
            'selection'  => 'Selection Board Review',
            'placement'  => 'Placement / Onboarding',
        ];

        $current = $application->current_stage;
        $stageKeys = array_keys($stagesFlow);
        $currentIndex = array_search($current, $stageKeys);

        // For rejected/withdrawn, show at current position in the flow
        $isTerminal = in_array($current, ['rejected', 'withdrawn']);

        $timeline = [];
        foreach ($stagesFlow as $key => $label) {
            $idx = array_search($key, $stageKeys);
            if ($isTerminal) {
                $status = 'pending'; // all grayed out if terminal
            } elseif ($idx < $currentIndex) {
                $status = 'completed';
            } elseif ($idx === $currentIndex) {
                $status = 'current';
            } else {
                $status = 'pending';
            }
            $timeline[] = [
                'stage'  => $key,
                'label'  => $label,
                'status' => $status,
            ];
        }

        // Get documents for this applicant
        $docs = ApplicantDocument::where('applicant_id', $application->applicant_id)->get();

        return Inertia::render('Recruitment/Public/TrackApplication', [
            'result' => [
                'application'    => $application,
                'current_stage'  => $current,
                'is_terminal'    => $isTerminal,
                'timeline'       => $timeline,
                'documents'      => $docs,
                'position'       => $application->jobVacancy?->jobItem?->position_title ?? '—',
                'office'         => $application->jobVacancy?->jobItem?->office?->name ?? '—',
                'applied_on'     => $application->application_date?->format('F j, Y'),
                'remarks'        => $application->remarks,
            ],
        ]);
    }
}
