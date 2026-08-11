<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Assignment;
use App\Models\Learn\Course;
use App\Models\Learn\Discussion;
use App\Models\Learn\File as LearnFile;
use App\Models\Learn\Page as LearnPage;
use App\Models\Learn\Quiz;
use App\Models\Learn\QuizQuestionBankItem;
use App\Models\Learn\RubricTemplate;
use App\Services\Learn\CourseCoverService;
use App\Services\Learn\CourseFileService;
use App\Services\Learn\CourseResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function __construct(
        private CourseResolver $resolver,
        private CourseFileService $files,
        private CourseCoverService $covers,
    ) {
    }

    /** GET /learn — "My Courses" for the signed-in faculty member. */
    public function index(): Response
    {
        $user = Auth::user();

        $courses = $user->hasPermission('learn.course.view.all')
            ? $this->resolver->allCoursesForCurrentSchoolYear()
            : $this->resolver->coursesForFaculty($user);

        $courses->load(['subject', 'section', 'schoolYear', 'modules.items']);

        return Inertia::render('Learn/Index', [
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id,
                'subject_name' => $c->subject->name,
                'section_name' => $c->section->sectionname,
                'grade_level' => $c->section->levelid,
                'status' => $c->status,
                'is_read_only' => $c->isReadOnly(),
                'cover_preset' => $c->cover_preset,
                'cover_photo_url' => $c->cover_photo_s3_key ? route('learn.cover.show', $c->id) : null,
                'setup_percent' => $c->setupProgress()['percent'],
            ])->values(),
        ]);
    }

    /** GET /learn/{course} */
    public function show(Course $course): Response
    {
        $user = Auth::user();
        abort_unless($course->canView($user), 403);

        $course->load(['subject', 'section', 'schoolYear', 'modules.items.itemable', 'announcements.postedBy']);
        $this->loadAssignmentRubrics($course);
        $this->loadQuizQuestions($course);
        $this->loadDiscussionPostCounts($course);

        return Inertia::render('Learn/Show', [
            'course' => $this->serializeCourse($course, $user),
            'rubric_templates' => RubricTemplate::where('user_id', $user->id)
                ->with('criteria')
                ->get()
                ->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'criteria' => $t->criteria->map(fn ($c) => [
                        'description' => $c->description, 'max_points' => (float) $c->max_points,
                    ])->values(),
                ])->values(),
            'quiz_question_bank' => QuizQuestionBankItem::where('user_id', $user->id)
                ->with('options')
                ->get()
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'question_type' => $item->question_type,
                    'prompt' => $item->prompt,
                    'points' => (float) $item->points,
                    'difficulty' => $item->difficulty,
                    'options' => $item->options->map(fn ($o) => [
                        'option_text' => $o->option_text, 'is_correct' => $o->is_correct,
                    ])->values(),
                ])->values(),
        ]);
    }

    /** PUT /learn/{course}/syllabus */
    public function updateSyllabus(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate(['syllabus_body' => 'nullable|string']);
        $course->update($validated);

        return back()->with('success', 'Syllabus updated.');
    }

    /** PATCH /learn/{course}/status */
    public function updateStatus(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate(['status' => 'required|in:draft,published']);
        $course->update($validated);

        return back()->with('success', $validated['status'] === 'published' ? 'Course published.' : 'Course moved back to draft.');
    }

    /** PUT /learn/{course}/cover */
    public function updateCover(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate([
            'preset' => 'nullable|string',
            'photo_base64' => 'nullable|string',
        ]);
        abort_if(empty($validated['preset']) && empty($validated['photo_base64']), 422, 'Provide a preset or a photo.');

        if (! empty($validated['photo_base64'])) {
            $this->covers->upload($course, $validated['photo_base64']);
        } else {
            $this->covers->setPreset($course, $validated['preset']);
        }

        return back()->with('success', 'Cover updated.');
    }

    /** GET /learn/{course}/cover */
    public function cover(Course $course)
    {
        abort_unless($course->canView(Auth::user()), 403);

        return $this->covers->streamResponse($course);
    }

    private function serializeCourse(Course $course, $user): array
    {
        return [
            'id' => $course->id,
            'subject_name' => $course->subject->name,
            'section_name' => $course->section->sectionname,
            'grade_level' => $course->section->levelid,
            'status' => $course->status,
            'syllabus_body' => $course->syllabus_body,
            'is_read_only' => $course->isReadOnly(),
            'cover_preset' => $course->cover_preset,
            'cover_photo_url' => $course->cover_photo_s3_key ? route('learn.cover.show', $course->id) : null,
            'setup_progress' => $course->setupProgress(),
            'can_edit' => $course->canEdit($user),
            'modules' => $course->modules->map(fn ($m) => [
                'id' => $m->id,
                'title' => $m->title,
                'position' => $m->position,
                'is_published' => $m->isPublished(),
                'items' => $m->items->map(fn ($i) => $this->serializeItem($i))->values(),
            ])->values(),
            'announcements' => $course->announcements->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'body' => $a->body,
                'posted_by' => $a->postedBy?->name,
                'posted_at' => $a->posted_at?->toIso8601String(),
            ])->values(),
        ];
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

    private function loadQuizQuestions(Course $course): void
    {
        foreach ($course->modules as $module) {
            foreach ($module->items as $item) {
                if ($item->itemable instanceof Quiz) {
                    $item->itemable->load('questions.options', 'questions.acceptedAnswers');
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

    private function serializeItem($item): array
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

        return [
            'id' => $item->id,
            'type' => $type,
            'position' => $item->position,
            'is_published' => $item->isPublished(),
            'title' => $itemable?->title,
            'body' => $itemable instanceof LearnPage ? $itemable->body : null,
            'video_url' => $itemable instanceof LearnPage ? $itemable->video_url : null,
            'file_url' => $itemable instanceof LearnFile
                ? route('learn.files.show', ['fileId' => $this->files->encodeFileId($itemable->s3_key)])
                : null,
            'assignment' => $itemable instanceof Assignment ? [
                'id' => $itemable->id,
                'instructions' => $itemable->instructions,
                'submission_type' => $itemable->submission_type,
                'due_at' => $itemable->due_at?->toIso8601String(),
                'max_score' => $itemable->maxScore(),
                'has_rubric' => $itemable->rubric !== null,
            ] : null,
            'quiz' => $itemable instanceof Quiz ? [
                'id' => $itemable->id,
                'instructions' => $itemable->instructions,
                'time_limit_minutes' => $itemable->time_limit_minutes,
                'max_attempts' => $itemable->max_attempts,
                'questions_to_draw' => $itemable->questions_to_draw,
                'shuffle_questions' => $itemable->shuffle_questions,
                'shuffle_options' => $itemable->shuffle_options,
                'due_at' => $itemable->due_at?->toIso8601String(),
                'is_locked' => $itemable->is_locked,
                'max_score' => $itemable->maxScore(),
                'question_count' => $itemable->questions->count(),
                'questions' => $itemable->questions->map(fn ($q) => [
                    'id' => $q->id,
                    'question_type' => $q->question_type,
                    'prompt' => $q->prompt,
                    'points' => (float) $q->points,
                    'difficulty' => $q->difficulty,
                    'options' => $q->options->map(fn ($o) => [
                        'id' => $o->id, 'option_text' => $o->option_text, 'is_correct' => $o->is_correct,
                    ])->values(),
                    'accepted_answers' => $q->acceptedAnswers->pluck('answer_text')->values(),
                ])->values(),
            ] : null,
            'discussion' => $itemable instanceof Discussion ? [
                'id' => $itemable->id,
                'prompt' => $itemable->prompt,
                'max_score' => $itemable->maxScore(),
                'post_count' => $itemable->posts->count(),
            ] : null,
        ];
    }
}
