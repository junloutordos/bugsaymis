<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageRead;
use App\Events\MessageSent;
use App\Events\NewMessageNotification;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatController extends Controller
{
    // ─── Inertia page ────────────────────────────────────────────────────────

    public function index(): \Inertia\Response
    {
        $user = Auth::user();

        return Inertia::render('Chat/Index', [
            'authUser' => [
                'id'     => $user->id,
                'name'   => $user->name,
                'avatar' => $user->profile_picture
                    ? $this->s3Url($user->profile_picture)
                    : null,
            ],
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function s3Url(?string $path): ?string
    {
        if (! $path) return null;
        if (str_starts_with($path, 'http')) return $path;

        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addHour());
        } catch (\Throwable) {
            return route('storage.proxy', ['path' => $path]);
        }
    }

    private function serializeMessage(Message $msg, int $userId = 0, ?Conversation $conv = null): array
    {
        $convType = $conv?->type ?? $msg->conversation?->type;

        // For group messages: count how many participants have seen this message
        $seenByCount = 0;
        if ($convType === 'group' && $conv) {
            $seenByCount = $conv->participants
                ->filter(fn (User $p) =>
                    $p->id !== $msg->sender_id &&
                    $p->pivot?->last_read_at &&
                    $p->pivot->last_read_at >= $msg->created_at
                )
                ->count();
        }

        return [
            'id'              => $msg->id,
            'conversation_id' => $msg->conversation_id,
            'sender_id'       => $msg->sender_id,
            'sender_name'     => $msg->sender?->name ?? 'Unknown',
            'sender_avatar'   => $this->s3Url($msg->sender?->profile_picture),
            'body'            => $msg->deleted_at ? null : $msg->body,
            'is_deleted'      => $msg->deleted_at !== null,
            'attachment_path' => $msg->deleted_at ? null : $this->s3Url($msg->attachment_path),
            'attachment_type' => $msg->deleted_at ? null : $msg->attachment_type,
            'read_at'         => $msg->read_at?->toIso8601String(),
            'seen_by_count'   => $seenByCount,
            'created_at'      => $msg->created_at->toIso8601String(),
        ];
    }

    private function serializeConversation(Conversation $conv, int $userId): array
    {
        $latest = $conv->latestMessage;

        // Check if current user has archived this conversation
        $pivot      = $conv->participants->firstWhere('id', $userId)?->pivot;
        $isArchived = $pivot?->archived_at !== null;

        return [
            'id'           => $conv->id,
            'type'         => $conv->type,
            'name'         => $conv->type === 'group'
                                ? $conv->name
                                : $conv->participants
                                    ->firstWhere('id', '!=', $userId)
                                    ?->name,
            'avatar'       => $conv->type === 'direct'
                                ? ($conv->participants->firstWhere('id', '!=', $userId)?->profile_picture
                                    ? $this->s3Url($conv->participants->firstWhere('id', '!=', $userId)->profile_picture)
                                    : null)
                                : null,
            'participants' => $conv->participants->map(fn (User $u) => [
                'id'     => $u->id,
                'name'   => $u->name,
                'avatar' => $u->profile_picture ? $this->s3Url($u->profile_picture) : null,
            ])->values(),
            'unread_count'   => $conv->unreadCountFor($userId),
            'is_archived'    => $isArchived,
            'latest_message' => $latest ? [
                'body'        => $latest->deleted_at ? null : $latest->body,
                'is_deleted'  => $latest->deleted_at !== null,
                'sender_id'   => $latest->sender_id,
                'created_at'  => $latest->created_at->toIso8601String(),
            ] : null,
            'updated_at' => $conv->updated_at->toIso8601String(),
        ];
    }

    // ─── Controllers ─────────────────────────────────────────────────────────

    public function getConversations(Request $request): JsonResponse
    {
        $userId   = Auth::id();
        $archived = $request->boolean('archived', false);

        $conversations = Conversation::forUser($userId)
            ->with([
                'participants:id,name,profile_picture',
                'latestMessage.sender:id,name',
            ])
            ->withCount(['messages as unread_count' => function ($q) use ($userId) {
                $q->where('sender_id', '!=', $userId)
                  ->whereNull('deleted_at')
                  ->whereNull('read_at');
            }])
            ->latest('updated_at')
            ->get()
            ->filter(function (Conversation $conv) use ($userId, $archived) {
                $pivot      = $conv->participants->firstWhere('id', $userId)?->pivot;
                $isArchived = $pivot?->archived_at !== null;
                return $archived ? $isArchived : ! $isArchived;
            })
            ->values();

        return response()->json([
            'conversations' => $conversations->map(
                fn ($c) => $this->serializeConversation($c, $userId)
            ),
        ]);
    }

    public function getMessages(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = Auth::id();

        abort_unless(
            $conversation->participants->pluck('id')->contains($userId),
            403,
            'You are not a participant of this conversation.'
        );

        $perPage = min((int) $request->input('per_page', 30), 100);

        // Load participants for seen_by_count computation
        $conversation->loadMissing('participants');

        $messages = $conversation->messages()
            ->with('sender:id,name,profile_picture')
            ->latest()
            ->paginate($perPage);

        $this->touchLastRead($conversation, $userId);

        return response()->json([
            'messages'   => collect($messages->items())
                ->map(fn ($m) => $this->serializeMessage($m, $userId, $conversation))
                ->values(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'per_page'     => $messages->perPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    public function startConversation(Request $request): JsonResponse
    {
        $request->validate([
            'type'              => 'required|in:direct,group',
            'user_id'           => 'required_if:type,direct|integer|exists:users,id|different:' . Auth::id(),
            'name'              => 'required_if:type,group|nullable|string|max:150',
            'participant_ids'   => 'required_if:type,group|array|min:1',
            'participant_ids.*' => 'integer|exists:users,id',
        ]);

        $userId = Auth::id();

        if ($request->type === 'direct') {
            $otherId  = (int) $request->user_id;
            $existing = Conversation::directBetween($userId, $otherId);

            if ($existing) {
                $existing->load('participants:id,name,profile_picture', 'latestMessage.sender:id,name');
                return response()->json([
                    'conversation' => $this->serializeConversation($existing, $userId),
                ], 200);
            }

            $conversation = DB::transaction(function () use ($userId, $otherId) {
                $conv = Conversation::create(['type' => 'direct', 'created_by' => $userId]);
                $conv->participants()->attach([$userId, $otherId]);
                return $conv;
            });
        } else {
            $participantIds = array_unique(array_merge([$userId], $request->participant_ids));

            $conversation = DB::transaction(function () use ($userId, $request, $participantIds) {
                $conv = Conversation::create([
                    'type'       => 'group',
                    'name'       => $request->name,
                    'created_by' => $userId,
                ]);
                $conv->participants()->attach($participantIds);
                return $conv;
            });
        }

        $conversation->load('participants:id,name,profile_picture', 'latestMessage');

        return response()->json([
            'conversation' => $this->serializeConversation($conversation, $userId),
        ], 201);
    }

    /**
     * POST /api/chat/conversations/{conversation}/messages
     *
     * Attachment sent as base64 data URI in JSON body (Cloudflare WAF blocks multipart).
     * Body: { body?: string, attachment_base64?: string, attachment_name?: string }
     */
    public function sendMessage(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = Auth::id();

        abort_unless(
            $conversation->participants->pluck('id')->contains($userId),
            403,
            'You are not a participant of this conversation.'
        );

        $request->validate([
            'body'              => 'nullable|string|max:5000',
            'attachment_base64' => 'nullable|string',
            'attachment_name'   => 'nullable|string|max:255',
        ]);

        if (! $request->filled('body') && ! $request->filled('attachment_base64')) {
            throw ValidationException::withMessages(['body' => 'A message body or attachment is required.']);
        }

        $attachmentPath = null;
        $attachmentType = null;

        if ($request->filled('attachment_base64')) {
            $dataUri = $request->input('attachment_base64');
            // Parse data:mime/type;base64,<data>
            if (preg_match('/^data:([^;]+);base64,(.+)$/', $dataUri, $m)) {
                $mime     = $m[1];
                $binary   = base64_decode($m[2]);
                $ext      = pathinfo($request->input('attachment_name', 'file'), PATHINFO_EXTENSION) ?: 'bin';
                $filename = 'chat/attachments/' . uniqid() . '.' . $ext;

                Storage::disk('s3')->put($filename, $binary);

                $attachmentPath = $filename;
                $attachmentType = str_starts_with($mime, 'image/') ? 'image' : 'file';
            }
        }

        $message = DB::transaction(function () use ($request, $conversation, $userId, $attachmentPath, $attachmentType) {
            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $userId,
                'body'            => $request->input('body', ''),
                'attachment_path' => $attachmentPath,
                'attachment_type' => $attachmentType,
            ]);
            $conversation->touch();
            return $msg;
        });

        $message->load('sender:id,name,profile_picture');
        $conversation->loadMissing('participants');
        $payload = $this->serializeMessage($message, $userId, $conversation);

        broadcast(new MessageSent($conversation, $payload))->toOthers();

        $conversation->participants()
            ->where('users.id', '!=', $userId)
            ->whereNull('conversation_user.left_at')
            ->get()
            ->each(function (User $recipient) use ($conversation, $payload) {
                broadcast(new NewMessageNotification($recipient->id, [
                    'conversation_id' => $conversation->id,
                    'message'         => $payload,
                ]));
            });

        return response()->json(['message' => $payload], 201);
    }

    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = Auth::id();

        abort_unless(
            $conversation->participants->pluck('id')->contains($userId),
            403
        );

        $this->touchLastRead($conversation, $userId);

        if ($conversation->type === 'direct') {
            $updated = Message::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $userId)
                ->whereNull('read_at')
                ->whereNull('deleted_at')
                ->get();

            if ($updated->isNotEmpty()) {
                Message::whereIn('id', $updated->pluck('id'))
                    ->update(['read_at' => now()]);

                broadcast(new MessageRead($conversation, $userId, $updated->pluck('id')->all()))->toOthers();
            }
        }

        return response()->json(['read' => true]);
    }

    /**
     * DELETE /api/chat/conversations/{conversation}/messages/{message}
     *
     * Soft-deletes a message. Only the sender can delete their own messages.
     * Broadcasts MessageDeleted so other participants update in real-time.
     */
    public function deleteMessage(Request $request, Conversation $conversation, Message $message): JsonResponse
    {
        $userId = Auth::id();

        abort_unless(
            $conversation->participants->pluck('id')->contains($userId),
            403
        );

        abort_unless(
            $message->sender_id === $userId,
            403,
            'You can only delete your own messages.'
        );

        abort_unless(
            $message->conversation_id === $conversation->id,
            404
        );

        $message->delete();

        broadcast(new MessageDeleted($conversation->id, $message->id))->toOthers();

        return response()->json(['deleted' => true]);
    }

    /**
     * POST /api/chat/conversations/{conversation}/archive
     *
     * Toggles archive status for the current user only.
     * Other participants are unaffected.
     */
    public function archiveConversation(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = Auth::id();

        abort_unless(
            $conversation->participants->pluck('id')->contains($userId),
            403
        );

        $pivot      = $conversation->participants->firstWhere('id', $userId)?->pivot;
        $isArchived = $pivot?->archived_at !== null;

        $conversation->participants()->updateExistingPivot($userId, [
            'archived_at' => $isArchived ? null : now(),
        ]);

        return response()->json(['archived' => ! $isArchived]);
    }

    public function listUsers(Request $request): JsonResponse
    {
        $search = $request->input('search', '');

        $users = User::where('id', '!=', Auth::id())
            ->when($search, fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->select('id', 'name', 'profile_picture', 'position', 'division_id')
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (User $u) => [
                'id'       => $u->id,
                'name'     => $u->name,
                'position' => $u->position,
                'avatar'   => $u->profile_picture ? $this->s3Url($u->profile_picture) : null,
            ]);

        return response()->json(['users' => $users]);
    }

    public function unreadCount(): JsonResponse
    {
        $userId = Auth::id();

        $count = DB::table('messages')
            ->join('conversation_user as cu', 'messages.conversation_id', '=', 'cu.conversation_id')
            ->where('cu.user_id', $userId)
            ->where('messages.sender_id', '!=', $userId)
            ->whereNull('messages.deleted_at')
            ->where(function ($q) {
                $q->whereNull('cu.last_read_at')
                  ->orWhereColumn('messages.created_at', '>', 'cu.last_read_at');
            })
            ->count();

        return response()->json(['unread_count' => (int) $count]);
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function touchLastRead(Conversation $conversation, int $userId): void
    {
        $conversation->participants()->updateExistingPivot($userId, [
            'last_read_at' => now(),
        ]);
    }
}
