<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Models\Learn\DiscussionPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscussionPostController extends Controller
{
    /** POST /learn/discussions/{discussion}/posts */
    public function store(Request $request, Discussion $discussion)
    {
        $user = Auth::user();
        $course = $discussion->course();
        abort_if(! $course, 404);
        abort_unless($course->canView($user), 403);

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
            'author_type' => 'faculty',
            'author_id' => $user->id,
            'body' => $validated['body'],
        ]);

        return back()->with('success', 'Reply posted.');
    }

    /** PUT /learn/discussion-posts/{post} */
    public function update(Request $request, DiscussionPost $post)
    {
        $user = Auth::user();
        abort_unless($post->author_type === 'faculty' && $post->author_id === $user->id, 403);
        abort_if($post->is_deleted, 403, 'This post has been deleted.');

        $validated = $request->validate(['body' => 'required|string']);
        $post->update(['body' => $validated['body']]);

        return back()->with('success', 'Post updated.');
    }

    /** DELETE /learn/discussion-posts/{post} */
    public function destroy(DiscussionPost $post)
    {
        $user = Auth::user();
        $isOwnPost = $post->author_type === 'faculty' && $post->author_id === $user->id;
        $canModerate = $post->discussion->canEdit($user);
        abort_unless($isOwnPost || $canModerate, 403);

        $post->update([
            'is_deleted' => true,
            'deleted_by_type' => 'faculty',
            'deleted_by_id' => $user->id,
        ]);

        return back()->with('success', 'Post deleted.');
    }
}
