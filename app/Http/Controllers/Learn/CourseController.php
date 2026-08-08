<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Course;
use App\Models\Learn\File as LearnFile;
use App\Models\Learn\Page as LearnPage;
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
    ) {
    }

    /** GET /learn — "My Courses" for the signed-in faculty member. */
    public function index(): Response
    {
        $user = Auth::user();

        $courses = $user->hasPermission('learn.course.view.all')
            ? $this->resolver->allCoursesForCurrentSchoolYear()
            : $this->resolver->coursesForFaculty($user);

        $courses->load(['subject', 'section', 'schoolYear']);

        return Inertia::render('Learn/Index', [
            'courses' => $courses->map(fn (Course $c) => [
                'id' => $c->id,
                'subject_name' => $c->subject->name,
                'section_name' => $c->section->sectionname,
                'grade_level' => $c->section->levelid,
                'status' => $c->status,
                'is_read_only' => $c->isReadOnly(),
            ])->values(),
        ]);
    }

    /** GET /learn/{course} */
    public function show(Course $course): Response
    {
        $user = Auth::user();
        abort_unless($course->canView($user), 403);

        $course->load(['subject', 'section', 'schoolYear', 'modules.items.itemable', 'announcements.postedBy']);

        return Inertia::render('Learn/Show', [
            'course' => $this->serializeCourse($course, $user),
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

    private function serializeItem($item): array
    {
        $itemable = $item->itemable;

        return [
            'id' => $item->id,
            'type' => $itemable instanceof LearnPage ? 'page' : 'file',
            'position' => $item->position,
            'is_published' => $item->isPublished(),
            'title' => $itemable?->title,
            'body' => $itemable instanceof LearnPage ? $itemable->body : null,
            'video_url' => $itemable instanceof LearnPage ? $itemable->video_url : null,
            'file_url' => $itemable instanceof LearnFile
                ? route('learn.files.show', ['fileId' => $this->files->encodeFileId($itemable->s3_key)])
                : null,
        ];
    }
}
