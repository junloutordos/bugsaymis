<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Assignment;
use App\Models\Learn\RubricScore;
use App\Models\Learn\Submission;
use App\Services\Learn\CourseFileService;
use App\Services\Learn\SubmissionRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AssignmentGradingController extends Controller
{
    public function __construct(
        private SubmissionRosterService $roster,
        private CourseFileService $files,
    ) {
    }

    /** GET /learn/assignments/{assignment}/submissions */
    public function index(Assignment $assignment): Response
    {
        $user = Auth::user();
        abort_unless($assignment->canEdit($user), 403);

        $assignment->load('rubric.criteria');

        $submissions = Submission::where('learn_assignment_id', $assignment->id)
            ->with('rubricScores')
            ->get()
            ->keyBy('student_id');

        $roster = $this->roster->rosterFor($assignment)->map(function ($row) use ($submissions) {
            $submission = $submissions->get($row['student_id']);

            return array_merge($row, [
                'text_body' => $submission?->text_body,
                'link_url' => $submission?->link_url,
                'file_url' => $submission?->learn_file_id
                    ? route('learn.submissions.file', $submission->id)
                    : null,
                'submitted_at' => $submission?->submitted_at?->toIso8601String(),
                'score' => $submission?->score !== null ? (float) $submission->score : null,
                'feedback_comment' => $submission?->feedback_comment,
                'is_graded' => $submission?->isGraded() ?? false,
                'rubric_scores' => $submission
                    ? $submission->rubricScores->mapWithKeys(
                        fn ($rs) => [(string) $rs->learn_rubric_criterion_id => (float) $rs->points_earned]
                    )
                    : (object) [],
            ]);
        });

        return Inertia::render('Learn/Grading', [
            'assignment' => [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'submission_type' => $assignment->submission_type,
                'max_score' => $assignment->maxScore(),
                'rubric' => $assignment->rubric ? [
                    'criteria' => $assignment->rubric->criteria->map(fn ($c) => [
                        'id' => $c->id, 'description' => $c->description, 'max_points' => (float) $c->max_points,
                    ])->values(),
                ] : null,
            ],
            'roster' => $roster->values(),
        ]);
    }

    /** PUT /learn/submissions/{submission}/grade */
    public function grade(Request $request, Submission $submission)
    {
        $user = Auth::user();
        $assignment = $submission->assignment;
        abort_unless($assignment->canEdit($user), 403);

        $rubric = $assignment->rubric;

        if ($rubric) {
            $criteria = $rubric->criteria;
            $criterionIds = $criteria->pluck('id')->all();

            $validated = $request->validate([
                'rubric_scores' => 'required|array',
                'rubric_scores.*' => 'required|numeric|min:0',
                'feedback_comment' => 'nullable|string',
            ]);

            $total = 0;
            foreach ($validated['rubric_scores'] as $criterionId => $points) {
                abort_unless(in_array((int) $criterionId, $criterionIds, true), 422);
                $criterion = $criteria->firstWhere('id', (int) $criterionId);
                abort_if((float) $points > (float) $criterion->max_points, 422);

                RubricScore::updateOrCreate(
                    ['learn_submission_id' => $submission->id, 'learn_rubric_criterion_id' => (int) $criterionId],
                    ['points_earned' => $points]
                );
                $total += $points;
            }

            $submission->update([
                'score' => $total,
                'feedback_comment' => $validated['feedback_comment'] ?? null,
                'graded_at' => now(),
                'graded_by' => $user->id,
            ]);
        } else {
            $rules = ['score' => ['required', 'numeric', 'min:0']];
            if ($assignment->points_possible !== null) {
                $rules['score'][] = 'max:' . $assignment->points_possible;
            }
            $rules['feedback_comment'] = 'nullable|string';

            $validated = $request->validate($rules);

            $submission->update([
                'score' => $validated['score'],
                'feedback_comment' => $validated['feedback_comment'] ?? null,
                'graded_at' => now(),
                'graded_by' => $user->id,
            ]);
        }

        return back()->with('success', 'Submission graded.');
    }

    /** POST /learn/submissions/{submission}/reopen */
    public function reopen(Submission $submission)
    {
        $user = Auth::user();
        abort_unless($submission->assignment->canEdit($user), 403);

        $submission->rubricScores()->delete();
        $submission->update(['score' => null, 'feedback_comment' => null, 'graded_at' => null, 'graded_by' => null]);

        return back()->with('success', 'Submission reopened for resubmission.');
    }

    /** GET /learn/submissions/{submission}/file */
    public function file(Submission $submission)
    {
        $user = Auth::user();
        abort_unless($submission->assignment->canEdit($user), 403);
        abort_if(! $submission->file, 404);

        return $this->files->streamResponse($submission->file);
    }
}
