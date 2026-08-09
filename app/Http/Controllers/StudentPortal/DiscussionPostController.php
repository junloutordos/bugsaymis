<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Models\Learn\DiscussionPost;
use App\Models\Student;
use Illuminate\Http\Request;

class DiscussionPostController extends Controller
{
    /** POST /student-portal/learn/discussions/{discussion}/posts */
    public function store(Request $request, Discussion $discussion)
    {
        $student = $this->currentStudent();
        $course = $discussion->course();
        abort_if(! $course, 404);
        abort_unless($course->isVisibleToStudent($student->id), 403);

        $validated = $request->validate([
            'parent_post_id' => 'nullable|integer|exists:learn_discussion_posts,id',
            'body' => 'required|string',
        ]);

        if (! empty($validated['parent_post_id'])) {
            $parentBelongsHere = DiscussionPost::where('id', $validated['parent_post_id'])
                ->where('learn_discussion_id', $discussion->id)
                ->exists();
            abort_unless($parentBelongsHere, 422, 'Invalid parent post.');
        }

        $discussion->posts()->create([
            'parent_post_id' => $validated['parent_post_id'] ?? null,
            'author_type' => 'student',
            'author_id' => $student->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Reply posted.');
    }

    /** PUT /student-portal/learn/discussion-posts/{post} */
    public function update(Request $request, DiscussionPost $post)
    {
        $student = $this->currentStudent();
        abort_unless($post->author_type === 'student' && $post->author_id === $student->id, 403);
        abort_if($post->is_deleted, 403, 'This post has been deleted.');

        $validated = $request->validate(['body' => 'required|string']);
        $post->update(['body' => $validated['body']]);

        return back()->with('success', 'Post updated.');
    }

    /** DELETE /student-portal/learn/discussion-posts/{post} */
    public function destroy(DiscussionPost $post)
    {
        $student = $this->currentStudent();
        abort_unless($post->author_type === 'student' && $post->author_id === $student->id, 403);

        $post->update([
            'is_deleted' => true,
            'deleted_by_type' => 'student',
            'deleted_by_id' => $student->id,
        ]);

        return back()->with('success', 'Post deleted.');
    }

    private function currentStudent(): Student
    {
        return Student::where('pisaysystemID', session('student_pisaysystemID'))->firstOrFail();
    }
}
