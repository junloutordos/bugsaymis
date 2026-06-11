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
        abort_if($vacancy->status !== 'open', 422, 'This vacancy is no longer accepting applications.');

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
            // Required documents — sent as base64 data URIs (Cloudflare blocks multipart uploads)
            'doc_application_letter_base64'   => 'required|string',
            'doc_application_letter_filename' => 'required|string|max:255',
            'doc_application_letter_mime'     => 'nullable|string|max:100',
            'doc_pds_base64'                  => 'required|string',
            'doc_pds_filename'                => 'required|string|max:255',
            'doc_pds_mime'                     => 'nullable|string|max:100',
            'doc_work_experience_base64'      => 'required|string',
            'doc_work_experience_filename'    => 'required|string|max:255',
            'doc_work_experience_mime'         => 'nullable|string|max:100',
            'doc_transcript_base64'           => 'required|string',
            'doc_transcript_filename'         => 'required|string|max:255',
            'doc_transcript_mime'              => 'nullable|string|max:100',
            'doc_eligibility_base64'          => 'required|string',
            'doc_eligibility_filename'        => 'required|string|max:255',
            'doc_eligibility_mime'             => 'nullable|string|max:100',
            'doc_ipcr_base64'                  => 'nullable|string',
            'doc_ipcr_filename'                => 'nullable|string|max:255',
            'doc_ipcr_mime'                     => 'nullable|string|max:100',
        ], [
            'doc_application_letter_base64.required' => 'Application Letter is required.',
            'doc_pds_base64.required'                 => 'Personal Data Sheet is required.',
            'doc_work_experience_base64.required'     => 'Work Experience Sheet is required.',
            'doc_transcript_base64.required'          => 'Transcript of Records is required.',
            'doc_eligibility_base64.required'         => 'Copy of Eligibility is required.',
        ]);

        $position = $vacancy->jobItem->position_title ?? 'Position';
        $lastName  = strtoupper($validated['last_name']);
        $firstName = strtoupper($validated['first_name']);

        // Upload files to Google Drive in a subfolder named: "LASTNAME, FIRSTNAME - Position"
        $driveFolder = "{$lastName}, {$firstName} - {$position}";

        $uploadedDocs = [];
        $docKeys = [
            'application_letter' => ['field' => 'doc_application_letter', 'exts' => ['pdf', 'doc', 'docx']],
            'pds'                => ['field' => 'doc_pds', 'exts' => ['pdf', 'doc', 'docx']],
            'work_experience'    => ['field' => 'doc_work_experience', 'exts' => ['pdf', 'doc', 'docx']],
            'transcript'         => ['field' => 'doc_transcript', 'exts' => ['pdf', 'doc', 'docx']],
            'eligibility'        => ['field' => 'doc_eligibility', 'exts' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']],
            'ipcr'               => ['field' => 'doc_ipcr', 'exts' => ['pdf', 'doc', 'docx']],
        ];

        foreach ($docKeys as $docType => $config) {
            $base64 = $validated["{$config['field']}_base64"] ?? null;
            if (! $base64) {
                continue;
            }

            $label    = self::REQUIRED_DOCUMENTS[$docType] ?? $docType;
            $filename = $validated["{$config['field']}_filename"];
            $mime     = $validated["{$config['field']}_mime"] ?? 'application/octet-stream';
            $ext      = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if (! in_array($ext, $config['exts'], true)) {
                return back()->withErrors(["{$config['field']}_base64" => "{$label} must be one of: " . implode(', ', $config['exts']) . '.']);
            }

            $raw = base64_decode(preg_replace('/^data:[^;]+;base64,/', '', $base64));

            if (strlen($raw) > 10 * 1024 * 1024) {
                return back()->withErrors(["{$config['field']}_base64" => "{$label} exceeds the 10MB limit."]);
            }

            $fileName = "[{$driveFolder}] {$label}.{$ext}";

            try {
                $result = $this->drive->uploadRaw($raw, $fileName, $mime);
                $uploadedDocs[$docType] = [
                    'drive_file_id' => $result['file_id'],
                    'drive_url'     => $result['link'],
                    'original_name' => $filename,
                    'file_size'     => strlen($raw),
                    'mime_type'     => $mime,
                ];
            } catch (\Throwable $e) {
                Log::error("Drive upload failed for {$docType}: " . $e->getMessage());
                return back()->withErrors(["doc_{$docType}" => "Failed to upload {$label}. Please try again."]);
            }
        }

        // Create applicant + application + document records in one transaction
        $application = DB::transaction(function () use ($validated, $vacancy, $uploadedDocs) {
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

            // Store Drive doc records
            foreach ($uploadedDocs as $docType => $meta) {
                ApplicantDocument::updateOrCreate(
                    ['applicant_id' => $applicant->id, 'document_type' => $docType],
                    array_merge($meta, ['file_path' => $meta['drive_url'], 'status' => 'pending'])
                );
            }

            return $this->workflow->apply($applicant, $vacancy, [
                'is_internal' => $validated['is_internal'] ?? false,
                'remarks'     => $validated['remarks'] ?? null,
            ]);
        });

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
