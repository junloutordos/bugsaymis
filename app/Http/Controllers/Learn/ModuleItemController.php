<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Models\Learn\Assignment;
use App\Models\Learn\Discussion;
use App\Models\Learn\Module;
use App\Models\Learn\ModuleItem;
use App\Models\Learn\Page;
use App\Models\Learn\Quiz;
use App\Models\Learn\RubricTemplate;
use App\Services\Learn\CourseFileService;
use App\Services\Learn\QuizQuestionFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ModuleItemController extends Controller
{
    public function __construct(
        private CourseFileService $files,
        private QuizQuestionFactory $questionFactory,
    ) {
    }

    /** POST /learn/modules/{module}/items/page */
    public function storePage(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'video_url' => 'nullable|url',
        ]);

        $page = Page::create($validated);
        $this->attachItem($module, $page);

        return back()->with('success', 'Page added.');
    }

    /** POST /learn/modules/{module}/items/file */
    public function storeFile(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'file_base64' => 'required|string',
        ]);

        $file = $this->files->upload($module->learn_course_id, $validated['title'], $validated['file_base64']);
        $this->attachItem($module, $file);

        return back()->with('success', 'File added.');
    }

    /** POST /learn/modules/{module}/items/assignment */
    public function storeAssignment(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'submission_type' => 'required|in:text,file,link',
            'points_possible' => 'nullable|numeric|min:0',
            'due_at' => 'nullable|date',
            'rubric_criteria' => 'nullable|array',
            'rubric_criteria.*.description' => 'required_with:rubric_criteria|string|max:255',
            'rubric_criteria.*.max_points' => 'required_with:rubric_criteria|numeric|min:0',
            'save_as_template' => 'nullable|boolean',
            'template_name' => 'required_if:save_as_template,true|nullable|string|max:255',
        ]);

        $hasRubric = ! empty($validated['rubric_criteria']);

        $assignment = Assignment::create([
            'title' => $validated['title'],
            'instructions' => $validated['instructions'] ?? null,
            'submission_type' => $validated['submission_type'],
            'points_possible' => $hasRubric ? null : ($validated['points_possible'] ?? null),
            'due_at' => $validated['due_at'] ?? null,
        ]);

        if ($hasRubric) {
            $rubric = $assignment->rubric()->create([]);
            foreach ($validated['rubric_criteria'] as $position => $criterion) {
                $rubric->criteria()->create([
                    'description' => $criterion['description'],
                    'max_points' => $criterion['max_points'],
                    'position' => $position,
                ]);
            }
        }

        if ($hasRubric && ($validated['save_as_template'] ?? false)) {
            $template = RubricTemplate::create([
                'user_id' => $user->id,
                'name' => $validated['template_name'],
            ]);
            foreach ($validated['rubric_criteria'] as $position => $criterion) {
                $template->criteria()->create([
                    'description' => $criterion['description'],
                    'max_points' => $criterion['max_points'],
                    'position' => $position,
                ]);
            }
        }

        $this->attachItem($module, $assignment);

        return back()->with('success', 'Assignment added.');
    }

    /** POST /learn/modules/{module}/items/quiz */
    public function storeQuiz(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'instructions' => 'nullable|string',
            'time_limit_minutes' => 'nullable|integer|min:1',
            'max_attempts' => 'nullable|integer|min:1',
            'questions_to_draw' => 'nullable|integer|min:1',
            'shuffle_questions' => 'nullable|boolean',
            'shuffle_options' => 'nullable|boolean',
            'due_at' => 'nullable|date',
            'questions' => 'nullable|array',
            'questions.*.question_type' => 'required_with:questions|in:multiple_choice,true_false,multiple_select,short_answer,essay',
            'questions.*.prompt' => 'required_with:questions|string',
            'questions.*.points' => 'required_with:questions|numeric|min:0',
            'questions.*.difficulty' => 'nullable|in:easy,medium,hard',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*.option_text' => 'required_with:questions.*.options|string|max:255',
            'questions.*.options.*.is_correct' => 'nullable|boolean',
            'questions.*.accepted_answers' => 'nullable|array',
            'questions.*.accepted_answers.*' => 'required_with:questions.*.accepted_answers|string|max:255',
            'questions.*.save_to_bank' => 'nullable|boolean',
            'questions.*.bank_name' => 'required_if:questions.*.save_to_bank,true|nullable|string|max:255',
        ]);

        $questions = $validated['questions'] ?? [];

        if (! empty($validated['questions_to_draw']) && count($questions) > 1) {
            $distinctPoints = collect($questions)->pluck('points')->unique();
            if ($distinctPoints->count() > 1) {
                throw ValidationException::withMessages([
                    'questions_to_draw' => 'When drawing a random subset of questions, every question must be worth the same points.',
                ]);
            }
        }

        $quiz = Quiz::create([
            'title' => $validated['title'],
            'instructions' => $validated['instructions'] ?? null,
            'time_limit_minutes' => $validated['time_limit_minutes'] ?? null,
            'max_attempts' => $validated['max_attempts'] ?? null,
            'questions_to_draw' => $validated['questions_to_draw'] ?? null,
            'shuffle_questions' => $validated['shuffle_questions'] ?? false,
            'shuffle_options' => $validated['shuffle_options'] ?? false,
            'due_at' => $validated['due_at'] ?? null,
        ]);

        foreach ($questions as $position => $questionData) {
            $this->questionFactory->create($quiz, $questionData, $position, $user);
        }

        $this->attachItem($module, $quiz);

        return back()->with('success', 'Quiz added.');
    }

    /** POST /learn/modules/{module}/items/discussion */
    public function storeDiscussion(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'prompt' => 'required|string',
            'points_possible' => 'nullable|numeric|min:0',
        ]);

        $discussion = Discussion::create($validated);
        $this->attachItem($module, $discussion);

        return back()->with('success', 'Discussion added.');
    }

    /** PATCH /learn/items/{item}/publish */
    public function togglePublish(ModuleItem $item)
    {
        $user = Auth::user();
        abort_unless($item->module->course->canEdit($user), 403);

        $item->update(['published_at' => $item->isPublished() ? null : now()]);

        return back()->with('success', $item->fresh()->isPublished() ? 'Item published.' : 'Item unpublished.');
    }

    /** PUT /learn/modules/{module}/items/reorder */
    public function reorder(Request $request, Module $module)
    {
        $user = Auth::user();
        abort_unless($module->course->canEdit($user), 403);

        $validated = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer|exists:learn_module_items,id',
        ]);

        foreach ($validated['item_ids'] as $position => $itemId) {
            ModuleItem::where('id', $itemId)->where('learn_module_id', $module->id)->update(['position' => $position]);
        }

        return back()->with('success', 'Items reordered.');
    }

    /** DELETE /learn/items/{item} */
    public function destroy(ModuleItem $item)
    {
        $user = Auth::user();
        abort_unless($item->module->course->canEdit($user), 403);

        $itemable = $item->itemable;
        $item->delete();
        $itemable?->delete();

        return back()->with('success', 'Item deleted.');
    }

    private function attachItem(Module $module, $itemable): ModuleItem
    {
        $position = (int) ($module->items()->max('position')) + 1;

        return $itemable->moduleItem()->create([
            'learn_module_id' => $module->id,
            'position' => $position,
        ]);
    }
}
