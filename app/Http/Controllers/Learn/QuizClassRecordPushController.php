<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Quiz;
use App\Services\Learn\ClassRecordPushService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizClassRecordPushController extends Controller
{
    public function __construct(private ClassRecordPushService $pushService)
    {
    }

    /** PUT /learn/quizzes/{quiz}/link */
    public function link(Request $request, Quiz $quiz)
    {
        $validated = $request->validate([
            'class_record_assessment_id' => 'required|integer|exists:class_record_assessments,id',
        ]);

        $this->pushService->link($quiz, $validated['class_record_assessment_id'], Auth::user());

        return back()->with('success', 'Linked to Class Record assessment.');
    }

    /** POST /learn/quizzes/{quiz}/push */
    public function push(Quiz $quiz)
    {
        $result = $this->pushService->push($quiz, Auth::user());

        $message = "Pushed {$result['pushed']} score(s) to Class Record.";
        if (! empty($result['skipped'])) {
            $message .= ' Skipped (not on quarter roster): ' . implode(', ', $result['skipped']) . '.';
        }

        return back()->with('success', $message);
    }
}
