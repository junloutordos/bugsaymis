<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Discussion;
use App\Services\Learn\ClassRecordPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DiscussionClassRecordPushController extends Controller
{
    public function __construct(private ClassRecordPushService $pushService)
    {
    }

    /** PUT /learn/discussions/{discussion}/link */
    public function link(Request $request, Discussion $discussion)
    {
        $validated = $request->validate([
            'class_record_assessment_id' => 'required|integer|exists:class_record_assessments,id',
        ]);

        $this->pushService->link($discussion, $validated['class_record_assessment_id'], Auth::user());

        return back()->with('success', 'Linked to Class Record assessment.');
    }

    /** POST /learn/discussions/{discussion}/push */
    public function push(Discussion $discussion)
    {
        $result = $this->pushService->push($discussion, Auth::user());

        $message = "Pushed {$result['pushed']} score(s) to Class Record.";
        if (! empty($result['skipped'])) {
            $message .= ' Skipped (not on quarter roster): ' . implode(', ', $result['skipped']) . '.';
        }

        return back()->with('success', $message);
    }
}
