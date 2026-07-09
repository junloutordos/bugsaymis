<?php

namespace App\Http\Controllers\Quiz;

use App\Http\Controllers\Controller;
use App\Models\Quiz\Quiz;
use App\Models\Quiz\QuizQuestion;
use App\Models\Quiz\QuizQuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class QuizController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Quiz::with('owner')->orderByDesc('updated_at');

        if (!$user->isSuperAdmin() && !$user->hasPermission('quiz.view_all')) {
            $query->where('owner_id', $user->id);
        }

        return Inertia::render('Quiz/Index', [
            'quizzes' => $query->get()->map(fn (Quiz $q) => [
                'id' => $q->id,
                'title' => $q->title,
                'status' => $q->status,
                'cover_image' => $q->coverImageUrl(),
                'question_count' => $q->questions()->count(),
                'source_type' => $q->source_type,
                'owner' => $q->owner?->name,
                'updated_at' => $q->updated_at,
            ]),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('Quiz/Builder', [
            'quiz' => null,
            'source_type' => $request->query('source_type'),
            'source_id' => $request->query('source_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'source_type' => ['nullable', 'in:activity,class_record'],
            'source_id' => ['nullable', 'integer'],
            'cover_image_base64' => ['nullable', 'string'],
        ]);

        $quiz = Quiz::create([
            'owner_id' => Auth::id(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'status' => Quiz::STATUS_DRAFT,
            'settings' => [
                'shuffle_answers' => false,
                'shuffle_questions' => false,
                'require_roster_join' => false,
            ],
        ]);

        if (!empty($data['cover_image_base64'])) {
            $quiz->update(['cover_image' => $this->storeBase64Image($data['cover_image_base64'], 'quiz-covers')]);
        }

        return redirect()->route('quiz.edit', $quiz)->with('success', 'Quiz created.');
    }

    public function edit(Quiz $quiz)
    {
        $this->authorizeOwner($quiz);

        $quiz->load(['questions.options']);

        return Inertia::render('Quiz/Builder', [
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'cover_image' => $quiz->coverImageUrl(),
                'status' => $quiz->status,
                'settings' => $quiz->settings,
                'source_type' => $quiz->source_type,
                'source_id' => $quiz->source_id,
                'questions' => $quiz->questions->map(fn (QuizQuestion $q) => [
                    'id' => $q->id,
                    'sort_order' => $q->sort_order,
                    'type' => $q->type,
                    'question_text' => $q->question_text,
                    'image' => $q->imageUrl(),
                    'time_limit_seconds' => $q->time_limit_seconds,
                    'points_base' => $q->points_base,
                    'double_points' => $q->double_points,
                    'options' => $q->options->map(fn (QuizQuestionOption $o) => [
                        'id' => $o->id,
                        'option_text' => $o->option_text,
                        'is_correct' => $o->is_correct,
                        'sort_order' => $o->sort_order,
                    ]),
                ]),
            ],
            'sessions' => $quiz->sessions()
                ->withCount('players')
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'game_pin' => $s->game_pin,
                    'status' => $s->status,
                    'player_count' => $s->players_count,
                    'created_at' => $s->created_at,
                    'host_url' => route('quiz.sessions.show', $s),
                    'report_url' => route('quiz.sessions.report', $s),
                ]),
        ]);
    }

    public function update(Request $request, Quiz $quiz)
    {
        $this->authorizeOwner($quiz);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:draft,published,archived'],
            'settings' => ['sometimes', 'array'],
            'cover_image_base64' => ['nullable', 'string'],
        ]);

        if (!empty($data['cover_image_base64'])) {
            $data['cover_image'] = $this->storeBase64Image($data['cover_image_base64'], 'quiz-covers');
            unset($data['cover_image_base64']);
        }

        $quiz->update($data);

        return back()->with('success', 'Quiz updated.');
    }

    public function destroy(Quiz $quiz)
    {
        $this->authorizeOwner($quiz);
        $quiz->delete();

        return redirect()->route('quiz.index')->with('success', 'Quiz deleted.');
    }

    public function duplicate(Quiz $quiz)
    {
        $this->authorizeOwner($quiz);
        $quiz->load('questions.options');

        $copy = $quiz->replicate(['status']);
        $copy->title = $quiz->title . ' (Copy)';
        $copy->status = Quiz::STATUS_DRAFT;
        $copy->owner_id = Auth::id();
        $copy->save();

        foreach ($quiz->questions as $question) {
            $newQuestion = $question->replicate();
            $newQuestion->quiz_id = $copy->id;
            $newQuestion->save();

            foreach ($question->options as $option) {
                $newOption = $option->replicate();
                $newOption->question_id = $newQuestion->id;
                $newOption->save();
            }
        }

        return redirect()->route('quiz.edit', $copy)->with('success', 'Quiz duplicated.');
    }

    // ── Questions ────────────────────────────────────────────────────────────

    public function storeQuestion(Request $request, Quiz $quiz)
    {
        $this->authorizeOwner($quiz);
        $data = $this->validateQuestion($request);

        DB::transaction(function () use ($quiz, $data) {
            $question = $quiz->questions()->create([
                'sort_order' => $quiz->questions()->max('sort_order') + 1,
                'type' => $data['type'],
                'question_text' => $data['question_text'],
                'time_limit_seconds' => $data['time_limit_seconds'],
                'points_base' => $data['points_base'],
                'double_points' => (bool) ($data['double_points'] ?? false),
                'image' => !empty($data['image_base64'])
                    ? $this->storeBase64Image($data['image_base64'], 'quiz-questions')
                    : null,
            ]);

            $this->syncOptions($question, $data['options'] ?? []);
        });

        return back()->with('success', 'Question added.');
    }

    public function updateQuestion(Request $request, Quiz $quiz, QuizQuestion $question)
    {
        $this->authorizeOwner($quiz);
        abort_unless($question->quiz_id === $quiz->id, 404);
        $data = $this->validateQuestion($request);

        DB::transaction(function () use ($question, $data) {
            $update = [
                'type' => $data['type'],
                'question_text' => $data['question_text'],
                'time_limit_seconds' => $data['time_limit_seconds'],
                'points_base' => $data['points_base'],
                'double_points' => (bool) ($data['double_points'] ?? false),
            ];

            if (!empty($data['image_base64'])) {
                $update['image'] = $this->storeBase64Image($data['image_base64'], 'quiz-questions');
            }

            $question->update($update);
            $this->syncOptions($question, $data['options'] ?? []);
        });

        return back()->with('success', 'Question updated.');
    }

    public function destroyQuestion(Quiz $quiz, QuizQuestion $question)
    {
        $this->authorizeOwner($quiz);
        abort_unless($question->quiz_id === $quiz->id, 404);
        $question->delete();

        return back()->with('success', 'Question removed.');
    }

    public function reorderQuestions(Request $request, Quiz $quiz)
    {
        $this->authorizeOwner($quiz);
        $ids = $request->validate(['question_ids' => ['required', 'array']])['question_ids'];

        foreach ($ids as $index => $id) {
            QuizQuestion::where('id', $id)->where('quiz_id', $quiz->id)->update(['sort_order' => $index]);
        }

        return back()->with('success', 'Order updated.');
    }

    // ── Private image proxies (S3 is private — never disk('public')) ──────────

    public function showCoverImage(Quiz $quiz): Response
    {
        return $this->streamS3($quiz->cover_image);
    }

    public function showQuestionImage(QuizQuestion $question): Response
    {
        return $this->streamS3($question->image);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private function authorizeOwner(Quiz $quiz): void
    {
        $user = Auth::user();
        abort_unless($user->isSuperAdmin() || $quiz->owner_id === $user->id, 403);
    }

    private function validateQuestion(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'in:mcq,true_false,checkbox,poll,open_ended'],
            'question_text' => ['required', 'string'],
            'time_limit_seconds' => ['required', 'integer', 'min:5', 'max:120'],
            'points_base' => ['required', 'integer', 'min:0', 'max:2000'],
            'double_points' => ['sometimes', 'boolean'],
            'image_base64' => ['nullable', 'string'],
            'options' => ['nullable', 'array'],
            'options.*.option_text' => ['required_with:options', 'string', 'max:255'],
            'options.*.is_correct' => ['boolean'],
        ]);
    }

    private function syncOptions(QuizQuestion $question, array $options): void
    {
        $question->options()->delete();

        foreach ($options as $index => $option) {
            $question->options()->create([
                'option_text' => $option['option_text'],
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'sort_order' => $index,
            ]);
        }
    }

    private function storeBase64Image(string $base64, string $folder): string
    {
        [$meta, $content] = explode(',', $base64, 2) + [null, null];
        $mime = preg_match('/data:(.*?);base64/', $meta ?? '', $m) ? $m[1] : 'image/jpeg';
        $ext = str_contains($mime, 'png') ? 'png' : (str_contains($mime, 'webp') ? 'webp' : 'jpg');

        $key = "{$folder}/" . Str::uuid() . ".{$ext}";
        Storage::disk('s3')->put($key, base64_decode($content));

        return $key;
    }

    private function streamS3(?string $key): Response
    {
        abort_if(!$key || !Storage::disk('s3')->exists($key), 404);

        return response(Storage::disk('s3')->get($key), 200)
            ->header('Content-Type', Storage::disk('s3')->mimeType($key));
    }
}
