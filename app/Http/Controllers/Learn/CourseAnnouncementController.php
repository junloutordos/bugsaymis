<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Course;
use App\Models\Learn\CourseAnnouncement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseAnnouncementController extends Controller
{
    /** POST /learn/{course}/announcements */
    public function store(Request $request, Course $course)
    {
        $user = Auth::user();
        abort_unless($course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $course->announcements()->create([
            'title' => $validated['title'],
            'body' => $validated['body'],
            'posted_by' => $user->id,
            'posted_at' => now(),
        ]);

        return back()->with('success', 'Announcement posted.');
    }

    /** PUT /learn/announcements/{announcement} */
    public function update(Request $request, CourseAnnouncement $announcement)
    {
        $user = Auth::user();
        abort_unless($announcement->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);
        $announcement->update($validated);

        return back()->with('success', 'Announcement updated.');
    }

    /** DELETE /learn/announcements/{announcement} */
    public function destroy(CourseAnnouncement $announcement)
    {
        $user = Auth::user();
        abort_unless($announcement->course->canEdit($user), 403);

        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }
}
