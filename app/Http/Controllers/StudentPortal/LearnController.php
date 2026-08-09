<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use App\Models\Learn\File as LearnFile;
use App\Models\Learn\Page as LearnPage;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizAttempt;
use App\Models\Learn\Submission;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Services\Learn\CourseFileService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LearnController extends Controller
{
    public function __construct(private CourseFileService $files)
    {
    }

    /** GET /student-portal/learn */
    public function index(): Response
    {
        $student = $this->currentStudent();

        $enrollments = StudentEnrollment::where('student_id', $student->id)
            ->where('status', 'enrolled')
            ->get(['school_year_id', 'section_id']);

        $courses = Course::with(['subject', 'section'])
            ->where('status', 'published')
            ->where(function ($query) use ($enrollments) {
                foreach ($enrollments as $enrollment) {
                    $query->orWhere(function ($q) use ($enrollment) {
                        $q->where('school_year_id', $enrollment->school_year_id)
                          ->where('section_id', $enrollment->section_id);
                    });
                }
            })
            ->get();

        return Inertia::render('StudentPortal/Learn/Index', [
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id,
                'subject_name' => $c->subject->name,
                'section_name' => $c->section->sectionname,
            ])->values(),
        ]);
    }

    /** GET /student-portal/learn/{course} */
    public function show(Course $course): Response
    {
        $student = $this->currentStudent();
        abort_unless($course->isVisibleToStudent($student->id), 403);

        $course->load([
            'subject', 'section',
            'modules' => fn ($q) => $q->whereNotNull('published_at'),
            'modules.items' => fn ($q) => $q->whereNotNull('published_at'),
            'modules.items.itemable',
            'announcements.postedBy',
        ]);
        $this->loadAssignmentRubrics($course);
        $this->loadQuizQuestionCounts($course);
        $this->loadDiscussionPostCounts($course);

        return Inertia::render('StudentPortal/Learn/Show', [
            'course' => [
                'id' => $course->id,
                'subject_name' => $course->subject->name,
                'section_name' => $course->section->sectionname,
                'syllabus_body' => $course->syllabus_body,
                'modules' => $course->modules->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'items' => $m->items->map(fn ($i) => $this->serializeItem($i, $student->id))->values(),
                ])->values(),
                'announcements' => $course->announcements->map(fn ($a) => [
                    'title' => $a->title,
                    'body' => $a->body,
                    'posted_by' => $a->postedBy?->name,
                    'posted_at' => $a->posted_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /** GET /student-portal/learn/file/{fileId} */
    public function file(string $fileId)
    {
        $student = $this->currentStudent();

        $s3Key = $this->files->decodeFileId($fileId);
        abort_if(! $s3Key, 404);

        $file = LearnFile::where('s3_key', $s3Key)->firstOrFail();
        $course = $file->moduleItem?->module->course;
        abort_if(! $course, 404);
        abort_unless($course->isVisibleToStudent($student->id), 403);

        return $this->files->streamResponse($file);
    }

    /** POST /student-portal/learn/assignments/{assignment}/submit */
    public function submit(Request $request, Assignment $assignment)
    {
        $student = $this->currentStudent();
        $course = $assignment->course();
        abort_if(! $course, 404);
        abort_unless($course->isVisibleToStudent($student->id), 403);

        $existing = Submission::where('learn_assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->first();
        abort_if($existing?->isGraded(), 403, 'This submission has already been graded and is locked.');

        $rules = match ($assignment->submission_type) {
            'text' => ['text_body' => 'required|string'],
            'link' => ['link_url' => 'required|url:http,https'],
            'file' => ['title' => 'required|string|max:255', 'file_base64' => 'required|string'],
        };
        $validated = $request->validate($rules);

        $data = ['submitted_at' => now()];
        match ($assignment->submission_type) {
            'text' => $data['text_body'] = $validated['text_body'],
            'link' => $data['link_url'] = $validated['link_url'],
            'file' => $data['learn_file_id'] = $this->files->upload(
                $course->id, $validated['title'], $validated['file_base64']
            )->id,
        };

        Submission::updateOrCreate(
            ['learn_assignment_id' => $assignment->id, 'student_id' => $student->id],
            $data
        );

        return back()->with('success', 'Submission saved.');
    }

    /** GET /student-portal/learn/submissions/{submission}/file */
    public function submissionFile(Submission $submission)
    {
        $student = $this->currentStudent();
        abort_unless($submission->student_id === $student->id, 403);
        abort_if(! $submission->file, 404);

        return $this->files->streamResponse($submission->file);
    }

    private function loadAssignmentRubrics(Course $course): void
    {
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Assignment) {
                    $item->itemable->load('rubric.criteria');
                }
            }
        }
    }

    private function loadQuizQuestionCounts(Course $course): void
    {
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Quiz) {
                    $item->itemable->load('questions');
                }
            }
        }
    }

    private function loadDiscussionPostCounts(Course $course): void
    {
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Discussion) {
                    $item->itemable->load('posts');
                }
            }
        }
    }

    private function serializeItem($item, int $studentId): array
    {
        $itemable = $item->itemable;

        $type = match (true) {
            $itemable instanceof LearnPage => 'page',
            $itemable instanceof LearnFile => 'file',
            $itemable instanceof Assignment => 'assignment',
            $itemable instanceof Quiz => 'quiz',
            $itemable instanceof Discussion => 'discussion',
            default => 'unknown',
        };

        $assignmentData = null;
        if ($itemable instanceof Assignment) {
            $submission = Submission::where('learn_assignment_id', $itemable->id)
                ->where('student_id', $studentId)
                ->first();

            $assignmentData = [
                'id' => $itemable->id,
                'instructions' => $itemable->instructions,
                'submission_type' => $itemable->submission_type,
                'due_at' => $itemable->due_at?->toIso8601String(),
                'max_score' => $itemable->maxScore(),
                'submission' => $submission ? [
                    'id' => $submission->id,
                    'text_body' => $submission->text_body,
                    'link_url' => $submission->link_url,
                    'file_url' => $submission->learn_file_id
                        ? route('student-portal.learn.submissions.file', $submission->id)
                        : null,
                    'submitted_at' => $submission->submitted_at->toIso8601String(),
                    'score' => $submission->score !== null ? (float) $submission->score : null,
                    'feedback_comment' => $submission->feedback_comment,
                    'is_graded' => $submission->isGraded(),
                    'is_late' => $submission->isLate(),
                ] : null,
            ];
        }

        $quizData = null;
        if ($itemable instanceof Quiz) {
            $attempts = QuizAttempt::where('learn_quiz_id', $itemable->id)
                ->where('student_id', $studentId)
                ->orderByDesc('attempt_number')
                ->get();
            $bestScore = $attempts->whereNotNull('score')->max('score');
            $inProgress = $attempts->first(fn ($a) => $a->submitted_at === null);

            $quizData = [
                'id' => $itemable->id,
                'instructions' => $itemable->instructions,
                'time_limit_minutes' => $itemable->time_limit_minutes,
                'max_attempts' => $itemable->max_attempts,
                'due_at' => $itemable->due_at?->toIso8601String(),
                'max_score' => $itemable->maxScore(),
                'question_count' => $itemable->questions_to_draw ?? $itemable->questions->count(),
                'attempts_used' => $attempts->count(),
                'best_score' => $bestScore !== null ? (float) $bestScore : null,
                'can_start_new_attempt' => $itemable->max_attempts === null || $attempts->count() < $itemable->max_attempts,
                'in_progress_attempt_id' => $inProgress?->id,
            ];
        }

        return [
            'id' => $item->id,
            'type' => $type,
            'title' => $itemable?->title,
            'body' => $itemable instanceof LearnPage ? $itemable->body : null,
            'video_url' => $itemable instanceof LearnPage ? $itemable->video_url : null,
            'file_url' => $itemable instanceof LearnFile
                ? route('student-portal.learn.file', ['fileId' => $this->files->encodeFileId($itemable->s3_key)])
                : null,
            'assignment' => $assignmentData,
            'quiz' => $quizData,
            'discussion' => $itemable instanceof Discussion ? [
                'id' => $itemable->id,
                'prompt' => $itemable->prompt,
                'max_score' => $itemable->maxScore(),
                'post_count' => $itemable->posts->count(),
            ] : null,
        ];
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
