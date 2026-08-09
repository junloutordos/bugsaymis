<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Services\Learn\DiscussionPostService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DiscussionController extends Controller
{
    public function __construct(private DiscussionPostService $postService)
    {
    }

    /** GET /learn/discussions/{discussion} */
    public function show(Discussion $discussion): Response
    {
        $user = Auth::user();
        $course = $discussion->course();
        abort_if(! $course, 404);
        abort_unless($course->canView($user), 403);

        return Inertia::render('Learn/Discussion', [
            'discussion' => [
                'id' => $discussion->id,
                'title' => $discussion->title,
                'prompt' => $discussion->prompt,
                'max_score' => $discussion->maxScore(),
                'can_edit' => $course->canEdit($user),
            ],
            'posts' => $this->postService->tree($discussion),
            'current_user_id' => $user->id,
        ]);
    }
}
