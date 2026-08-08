<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Learn\Course;
use App\Models\Learn\File as LearnFile;
use App\Models\Learn\Page as LearnPage;
use App\Models\Registrar\StudentEnrollment;
use App\Models\Student;
use App\Services\Learn\CourseFileService;
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

        return Inertia::render('StudentPortal/Learn/Show', [
            'course' => [
                'id' => $course->id,
                'subject_name' => $course->subject->name,
                'section_name' => $course->section->sectionname,
                'syllabus_body' => $course->syllabus_body,
                'modules' => $course->modules->map(fn ($m) => [
                    'id' => $m->id,
                    'title' => $m->title,
                    'items' => $m->items->map(fn ($i) => $this->serializeItem($i))->values(),
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

    private function serializeItem($item): array
    {
        $itemable = $item->itemable;

        return [
            'id' => $item->id,
            'type' => $itemable instanceof LearnPage ? 'page' : 'file',
            'title' => $itemable?->title,
            'body' => $itemable instanceof LearnPage ? $itemable->body : null,
            'video_url' => $itemable instanceof LearnPage ? $itemable->video_url : null,
            'file_url' => $itemable instanceof LearnFile
                ? route('student-portal.learn.file', ['fileId' => $this->files->encodeFileId($itemable->s3_key)])
                : null,
        ];
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
