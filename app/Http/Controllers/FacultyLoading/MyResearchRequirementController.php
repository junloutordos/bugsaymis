<?php

namespace App\Http\Controllers\FacultyLoading;

use App\Http\Controllers\Controller;
use App\Models\FacultyLoading\ResearchAdvisory;
use App\Models\FacultyLoading\ResearchRequirementAssignment;
use App\Models\FacultyLoading\ResearchRequirementSubmission;
use App\Models\FacultyLoading\ResearchRequirementSubmissionFile;
use App\Jobs\NotifyResearchSubmissionReceived;
use App\Services\FacultyLoading\ResearchSubmissionFileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MyResearchRequirementController extends Controller
{
    public function __construct(private readonly ResearchSubmissionFileService $files) {}

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $myGroupIds = ResearchAdvisory::where('user_id', $userId)
            ->where('status', '<>', 'dropped')
            ->whereNotNull('research_group_id')
            ->pluck('research_group_id');

        $assignments = ResearchRequirementAssignment::visible()
            ->whereIn('research_group_id', $myGroupIds)
            ->whereHas('requirement', fn ($q) => $q->where('status', 'active'))
            ->with(['requirement', 'researchGroup', 'submissions.submittedBy:id,name', 'submissions.files'])
            ->get()
            ->map(fn ($a) => $this->mapMyAssignment($a));

        return Inertia::render('FacultyLoading/MyResearchRequirements', [
            'assignments' => $assignments,
        ]);
    }

    public function submit(Request $request, ResearchRequirementAssignment $assignment): RedirectResponse
    {
        $fileService = $this->files;
        $userId = $request->user()->id;

        $isMember = ResearchAdvisory::where('user_id', $userId)
            ->where('research_group_id', $assignment->research_group_id)
            ->where('status', '<>', 'dropped')
            ->exists();
        abort_unless($isMember, 403);

        $requirement = $assignment->requirement;

        if ($assignment->status === 'pending' && ! $requirement->allow_late_submission && now()->gt($requirement->due_at)) {
            return back()->withErrors(['due_at' => 'The deadline for this requirement has passed and late submissions are not allowed.']);
        }

        $data = $request->validate([
            'notes'        => 'nullable|string|max:2000',
            'files'        => 'required|array|min:1|max:' . $requirement->max_files,
            'files.*.data' => 'required|string',
            'files.*.name' => 'required|string|max:255',
        ]);

        $stored = [];
        try {
            foreach ($data['files'] as $file) {
                $stored[] = $fileService->decodeAndStore($file['data'], $file['name'], $requirement->accepted_file_types);
            }
        } catch (ValidationException $e) {
            foreach ($stored as $s) {
                Storage::disk('s3')->delete($s['s3_key']);
            }
            throw $e;
        }

        DB::transaction(function () use ($assignment, $userId, $data, $requirement, $stored) {
            $submission = ResearchRequirementSubmission::create([
                'research_requirement_assignment_id' => $assignment->id,
                'submitted_by' => $userId,
                'notes'        => $data['notes'] ?? null,
                'submitted_at' => now(),
                'is_late'      => now()->gt($requirement->due_at),
            ]);

            foreach ($stored as $s) {
                ResearchRequirementSubmissionFile::create(array_merge($s, [
                    'research_requirement_submission_id' => $submission->id,
                ]));
            }

            $assignment->update(['status' => 'submitted']);
            NotifyResearchSubmissionReceived::dispatch($submission->id);
        });

        return back()->with('success', 'Submission uploaded.');
    }

    public function file(Request $request, string $fileId)
    {
        $s3Key = $this->files->decodeKey($fileId);
        if (! $s3Key) {
            abort(404);
        }

        $file = ResearchRequirementSubmissionFile::where('s3_key', $s3Key)->first();
        if (! $file) {
            abort(404);
        }

        $assignment = $file->submission->assignment;
        $userId     = $request->user()->id;

        $isCoordinator = $request->user()->hasAnyPermission(['faculty_loading.manage', 'faculty_loading.research_advisories']);
        $isGroupMember = ResearchAdvisory::where('user_id', $userId)
            ->where('research_group_id', $assignment->research_group_id)
            ->exists();

        abort_unless($isCoordinator || $isGroupMember, 403);

        if (! Storage::disk('s3')->exists($s3Key)) {
            abort(404);
        }

        $contents = Storage::disk('s3')->get($s3Key);

        return response($contents, 200)
            ->header('Content-Type', $file->mime_type)
            ->header('Content-Disposition', 'attachment; filename="' . addslashes($file->original_filename) . '"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    private function mapMyAssignment(ResearchRequirementAssignment $a): array
    {
        $latest = $a->submissions->first();

        return [
            'id'          => $a->id,
            'status'      => $a->status,
            'requirement' => [
                'id'                    => $a->requirement->id,
                'title'                 => $a->requirement->title,
                'description'           => $a->requirement->description,
                'due_at'                => $a->requirement->due_at->toIso8601String(),
                'allow_late_submission' => $a->requirement->allow_late_submission,
                'accepted_file_types'   => $a->requirement->accepted_file_types,
                'max_files'             => $a->requirement->max_files,
            ],
            'research_group' => [
                'id'          => $a->researchGroup->id,
                'title'       => $a->researchGroup->title,
                'grade_level' => $a->researchGroup->grade_level,
            ],
            'latest_submission' => $latest ? [
                'id'             => $latest->id,
                'notes'          => $latest->notes,
                'submitted_at'   => $latest->submitted_at->toIso8601String(),
                'is_late'        => $latest->is_late,
                'review_status'  => $latest->review_status,
                'review_comment' => $latest->review_comment,
                'submitted_by'   => $latest->submittedBy?->name,
                'files'          => $latest->files->map(fn ($f) => ['id' => $this->files->encodeKey($f->s3_key), 'name' => $f->original_filename])->values()->all(),
            ] : null,
        ];
    }
}
