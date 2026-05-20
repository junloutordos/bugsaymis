<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\MessageRead;
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

    /**
     * GET /chat — renders the messenger Inertia page.
     */
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

    /**
     * Generate a temporary (1-hour) presigned S3 URL for a private file.
     * Falls back to the storage proxy route when S3 is not configured (local dev).
     */
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

    private function serializeMessage(Message $msg): array
    {
        return [
            'id'              => $msg->id,
            'conversation_id' => $msg->conversation_id,
            'sender_id'       => $msg->sender_id,
            'sender_name'     => $msg->sender?->name ?? 'Unknown',
            'sender_avatar'   => $this->s3Url($msg->sender?->profile_picture),
            'body'            => $msg->body,
            'attachment_path' => $this->s3Url($msg->attachment_path),
            'attachment_type' => $msg->attachment_type,
            'read_at'         => $msg->read_at?->toIso8601String(),
            'created_at'      => $msg->created_at->toIso8601String(),
        ];
    }

    /**
     * Serialize a conversation for the sidebar list.
     */
    private function serializeConversation(Conversation $conv, int $userId): array
    {
        $latest = $conv->latestMessage;

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
            'unread_count' => $conv->unreadCountFor($userId),
            'latest_message' => $latest ? [
                'body'       => $latest->body,
                'sender_id'  => $latest->sender_id,
                'created_at' => $latest->created_at->toIso8601String(),
            ] : null,
            'updated_at'   => $conv->updated_at->toIso8601String(),
        ];
    }

    // ─── Controllers ─────────────────────────────────────────────────────────

    /**
     * GET /api/chat/conversations
     *
     * Returns all conversations for the authenticated user,
     * ordered by latest activity, with unread counts.
     */
    public function getConversations(Request $request): JsonResponse
    {
        $userId = Auth::id();

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
            ->get();

        return response()->json([
            'conversations' => $conversations->map(
                fn ($c) => $this->serializeConversation($c, $userId)
            ),
        ]);
    }

    /**
     * GET /api/chat/conversations/{conversation}/messages
     *
     * Returns paginated messages for a conversation (cursor-based, newest first).
     * Also marks the conversation as read for this user.
     */
    public function getMessages(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = Auth::id();

        // Authorization: user must be a participant
        abort_unless(
            $conversation->participants->pluck('id')->contains($userId),
            403,
            'You are not a participant of this conversation.'
        );

        $perPage = min((int) $request->input('per_page', 30), 100);

        $messages = $conversation->activeMessages()
            ->with('sender:id,name,profile_picture')
            ->latest()
            ->paginate($perPage);

        // Eagerly mark as read (fire-and-forget — real read receipt in markAsRead)
        $this->touchLastRead($conversation, $userId);

        return response()->json([
            'messages'   => collect($messages->items())->map(fn ($m) => $this->serializeMessage($m))->values(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'last_page'    => $messages->lastPage(),
                'per_page'     => $messages->perPage(),
                'total'        => $messages->total(),
            ],
        ]);
    }

    /**
     * POST /api/chat/conversations
     *
     * Find or create a direct conversation with another user,
     * or create a new group conversation.
     */
    public function startConversation(Request $request): JsonResponse
    {
        $request->validate([
            'type'         => 'required|in:direct,group',
            'user_id'      => 'required_if:type,direct|integer|exists:users,id|different:' . Auth::id(),
            'name'         => 'required_if:type,group|nullable|string|max:150',
            'participant_ids' => 'required_if:type,group|array|min:1',
            'participant_ids.*' => 'integer|exists:users,id',
        ]);

        $userId = Auth::id();

        if ($request->type === 'direct') {
            $otherId = (int) $request->user_id;

            // Return existing DM if one already exists
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
            $participantIds = array_unique(
                array_merge([$userId], $request->participant_ids)
            );

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
     * Send a message (text and/or attachment) to a conversation.
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
            'body'       => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,gif,pdf,doc,docx,xlsx,zip',
        ]);

        if (! $request->filled('body') && ! $request->hasFile('attachment')) {
            throw ValidationException::withMessages(['body' => 'A message body or attachment is required.']);
        }

        $attachmentPath = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $file           = $request->file('attachment');
            $attachmentPath = $file->store('chat/attachments', 'public');
            $attachmentType = str_contains($file->getMimeType(), 'image') ? 'image' : 'file';
        }

        $message = DB::transaction(function () use ($request, $conversation, $userId, $attachmentPath, $attachmentType) {
            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $userId,
                'body'            => $request->input('body', ''),
                'attachment_path' => $attachmentPath,
                'attachment_type' => $attachmentType,
            ]);

            // Bump the conversation's updated_at so it rises to the top of the list
            $conversation->touch();

            return $msg;
        });

        $message->load('sender:id,name,profile_picture');
        $payload = $this->serializeMessage($message);

        // Broadcast to all participants in real-time (Phase 5)
        broadcast(new MessageSent($conversation, $payload))->toOthers();

        // Notify every recipient on their personal channel (Phase 8)
        // This fires for users NOT on the Chat page, powering sidebar badge + browser notifications.
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

    /**
     * POST /api/chat/conversations/{conversation}/read
     *
     * Mark all messages in this conversation as read for the current user.
     * Updates conversation_user.last_read_at and message.read_at for DMs.
     */
    public function markAsRead(Request $request, Conversation $conversation): JsonResponse
    {
        $userId = Auth::id();

        abort_unless(
            $conversation->participants->pluck('id')->contains($userId),
            403
        );

        $this->touchLastRead($conversation, $userId);

        // For 1-on-1 DMs: stamp read_at on unread messages from the other party
        if ($conversation->type === 'direct') {
            $updated = Message::where('conversation_id', $conversation->id)
                ->where('sender_id', '!=', $userId)
                ->whereNull('read_at')
                ->whereNull('deleted_at')
                ->get();

            if ($updated->isNotEmpty()) {
                Message::whereIn('id', $updated->pluck('id'))
                    ->update(['read_at' => now()]);

                // Broadcast read receipts to the sender(s)
                broadcast(new MessageRead($conversation, $userId, $updated->pluck('id')->all()))->toOthers();
            }
        }

        return response()->json(['read' => true]);
    }

    /**
     * GET /api/chat/users
     *
     * List all users the current user can start a conversation with.
     */
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

    // ─── Private helpers ──────────────────────────────────────────────────────

    private function touchLastRead(Conversation $conversation, int $userId): void
    {
        $conversation->participants()->updateExistingPivot($userId, [
            'last_read_at' => now(),
        ]);
    }

    // ─── Notifications ────────────────────────────────────────────────────────

    /**
     * GET /api/chat/unread-count
     *
     * Returns the total number of unread messages across all conversations
     * for the authenticated user. Used by the sidebar badge.
     */
    public function unreadCount(): JsonResponse
    {
        $userId = Auth::id();

        $count = Conversation::forUser($userId)
            ->withCount(['messages as unread_count' => function ($q) use ($userId) {
                $q->where('sender_id', '!=', $userId)
                  ->whereNull('deleted_at')
                  ->whereNull('read_at');
            }])
            ->get()
            ->sum('unread_count');

        return response()->json(['unread_count' => (int) $count]);
    }
}
