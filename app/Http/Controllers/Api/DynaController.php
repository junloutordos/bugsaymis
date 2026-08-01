<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Atlas\DynaConversation;
use App\Services\Atlas\Dyna\DynaOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynaController extends Controller
{
    public function __construct(private readonly DynaOrchestratorService $orchestrator) {}

    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['nullable', 'integer'],
            'message' => ['required', 'string', 'max:4000'],
        ]);

        $user = $request->user();

        if (! empty($validated['conversation_id'])) {
            $conversation = DynaConversation::where('user_id', $user->id)
                ->findOrFail($validated['conversation_id']);
        } else {
            $conversation = DynaConversation::create([
                'user_id' => $user->id,
                'title' => str($validated['message'])->limit(60),
            ]);
        }

        $answer = $this->orchestrator->reply($user, $conversation, $validated['message']);

        return response()->json(['conversation_id' => $conversation->id, 'answer' => $answer]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $conversations = DynaConversation::where('user_id', $request->user()->id)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'updated_at']);

        return response()->json($conversations);
    }
}
