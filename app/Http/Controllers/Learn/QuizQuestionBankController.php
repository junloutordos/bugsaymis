<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\QuizQuestionBankItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QuizQuestionBankController extends Controller
{
    /** PUT /learn/quiz-question-bank/{item} */
    public function update(Request $request, QuizQuestionBankItem $item)
    {
        abort_unless($item->user_id === Auth::id(), 403);

        $validated = $request->validate(['name' => 'required|string|max:255']);
        $item->update($validated);

        return back()->with('success', 'Bank question renamed.');
    }

    /** DELETE /learn/quiz-question-bank/{item} */
    public function destroy(QuizQuestionBankItem $item)
    {
        abort_unless($item->user_id === Auth::id(), 403);

        $item->delete();

        return back()->with('success', 'Bank question deleted.');
    }
}
