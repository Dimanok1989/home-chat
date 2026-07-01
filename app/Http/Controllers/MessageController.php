<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Models\ChatRoom;
use App\Support\BroadcastsChatRoomCreated;
use App\Support\BroadcastsUnreadCount;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    use BroadcastsChatRoomCreated;
    use BroadcastsUnreadCount;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $room = $this->resolveAccessibleRoom($request);
        $room->ensureMembership($user->id);
        $clearedAt = $room->clearedAtForUser($user->id);

        $currentUserId = $user->id;
        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $beforeId = $request->query('before_id');
        $context = $request->query('context');

        if ($context === 'unread') {
            return $this->indexWithUnreadContext($room, $user, $clearedAt, $limit);
        }

        $query = Message::query()
            ->where('chat_room_id', $room->id);

        if ($clearedAt !== null) {
            $query->where('created_at', '>', $clearedAt);
        }

        if ($beforeId !== null && $beforeId !== '') {
            $query->where('id', '<', (int) $beforeId);
        }

        $pageIds = (clone $query)
            ->orderByDesc('id')
            ->limit($limit + 1)
            ->pluck('id');

        $hasMore = $pageIds->count() > $limit;

        if ($hasMore) {
            $pageIds = $pageIds->take($limit);
        }

        $messages = Message::query()
            ->whereIn('id', $pageIds)
            ->with(['attachments', 'user', 'replyTo.user', 'replyTo.attachments'])
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message) => $this->formatMessage($message, $currentUserId));

        return response()->json([
            'messages' => $messages,
            'has_more' => $hasMore,
            'last_read_message_id' => $room->lastReadMessageIdFor($user->id),
            'first_unread_message_id' => $room->firstUnreadMessageIdFor($user->id),
            'unread_count' => $room->unreadCountFor($user->id),
        ]);
    }

    /**
     * @return JsonResponse
     */
    private function indexWithUnreadContext(
        ChatRoom $room,
        User $user,
        ?\Illuminate\Support\Carbon $clearedAt,
        int $contextLimit,
    ): JsonResponse {
        $currentUserId = $user->id;
        $lastReadId = $room->lastReadMessageIdFor($user->id) ?? 0;

        $baseQuery = Message::query()->where('chat_room_id', $room->id);

        if ($clearedAt !== null) {
            $baseQuery->where('created_at', '>', $clearedAt);
        }

        $unreadIds = (clone $baseQuery)
            ->where('id', '>', $lastReadId)
            ->orderBy('id')
            ->pluck('id');

        $readContextIds = (clone $baseQuery)
            ->where('id', '<=', $lastReadId)
            ->orderByDesc('id')
            ->limit($contextLimit + 1)
            ->pluck('id');

        $hasMore = $readContextIds->count() > $contextLimit;

        if ($hasMore) {
            $readContextIds = $readContextIds->take($contextLimit);
        }

        $allIds = $readContextIds->merge($unreadIds)->unique()->sort()->values();

        $messages = Message::query()
            ->whereIn('id', $allIds)
            ->with(['attachments', 'user', 'replyTo.user', 'replyTo.attachments'])
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message) => $this->formatMessage($message, $currentUserId));

        return response()->json([
            'messages' => $messages,
            'has_more' => $hasMore,
            'last_read_message_id' => $lastReadId > 0 ? $lastReadId : null,
            'first_unread_message_id' => $room->firstUnreadMessageIdFor($user->id),
            'unread_count' => $room->unreadCountFor($user->id),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['nullable', 'integer', 'exists:chat_rooms,id', 'required_without:user_id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without:room_id'],
            'body' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:5120'],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (isset($validated['user_id'])) {
            $targetUserId = (int) $validated['user_id'];

            if ($targetUserId === $user->id) {
                throw ValidationException::withMessages([
                    'user_id' => ['Нельзя создать диалог с самим собой.'],
                ]);
            }

            $room = ChatRoom::findOrCreateDirect($user->id, $targetUserId);
        } else {
            $room = ChatRoom::query()->findOrFail((int) $validated['room_id']);
        }

        if (! $room->isAccessibleBy($user)) {
            if (isset($validated['user_id'])) {
                $room->users()->syncWithoutDetaching([$user->id, (int) $validated['user_id']]);
            }
        }

        if (! $room->isAccessibleBy($user)) {
            abort(403, 'Нет доступа к этому чату.');
        }

        $body = isset($validated['body']) ? trim($validated['body']) : '';
        $hasImage = $request->hasFile('image');

        if ($body === '' && ! $hasImage) {
            throw ValidationException::withMessages([
                'body' => ['Укажите текст сообщения или прикрепите изображение.'],
            ]);
        }

        $replyToId = isset($validated['reply_to_id']) ? (int) $validated['reply_to_id'] : null;

        if ($replyToId !== null) {
            $replyTo = Message::query()->find($replyToId);
            $clearedAt = $room->clearedAtForUser($user->id);

            if ($replyTo === null
                || $replyTo->chat_room_id !== $room->id
                || $replyTo->user_id === null
                || ($clearedAt !== null && $replyTo->created_at <= $clearedAt)) {
                throw ValidationException::withMessages([
                    'reply_to_id' => ['Нельзя ответить на это сообщение.'],
                ]);
            }
        }

        $currentUserId = $user->id;
        $wasFirstMessage = $room->last_message_at === null;

        $message = DB::transaction(function () use ($body, $hasImage, $request, $currentUserId, $room, $replyToId) {
            $message = Message::query()->create([
                'user_id' => $currentUserId,
                'chat_room_id' => $room->id,
                'reply_to_id' => $replyToId,
                'body' => $body !== '' ? $body : null,
            ]);

            if ($hasImage) {
                $file = $request->file('image');
                $extension = $file->guessExtension() ?? 'jpg';
                $path = $file->storeAs(
                    'chat/attachments',
                    Str::uuid().'.'.$extension,
                    'local',
                );

                $message->attachments()->create([
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'image/jpeg',
                    'size' => $file->getSize(),
                    'access_token' => Str::random(64),
                    'token_expires_at' => now()->addHours(24),
                ]);
            }

            $room->update(['last_message_at' => $message->created_at]);

            return $message->load(['attachments', 'user', 'replyTo.user', 'replyTo.attachments']);
        });

        broadcast(new MessageSent($message));

        $room->markReadUpTo($currentUserId, $message->id);
        $this->broadcastUnreadCountsForRoom($room->fresh(['users']));

        if ($wasFirstMessage) {
            $this->broadcastChatRoomCreated($room, $currentUserId);
        }

        $response = [
            'message' => $this->formatMessage($message, $currentUserId),
        ];

        if (isset($validated['user_id'])) {
            $room->load(['users']);
            $response['room'] = $room->toApiArray($user);
        }

        return response()->json($response, 201);
    }

    public function destroy(Message $message): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($message->user_id !== $user->id) {
            abort(403);
        }

        $room = $message->chatRoom;

        if ($room === null || ! $room->isAccessibleBy($user)) {
            abort(403);
        }

        $messageId = $message->id;
        $roomId = $room->id;
        $message->delete();

        $lastMessage = Message::query()
            ->where('chat_room_id', $roomId)
            ->with(['attachments', 'user'])
            ->latest('id')
            ->first();

        $lastMessageAt = $lastMessage?->created_at ?? $room->created_at;
        $room->update(['last_message_at' => $lastMessageAt]);

        $lastMessagePayload = $lastMessage ? Message::previewPayload($lastMessage) : null;
        $lastMessageAtIso = $lastMessageAt->toIso8601String();

        broadcast(new MessageDeleted($messageId, $roomId, $lastMessagePayload, $lastMessageAtIso));

        return response()->json([
            'id' => $messageId,
            'chat_room_id' => $roomId,
            'last_message' => $lastMessagePayload,
            'last_message_at' => $lastMessageAtIso,
        ]);
    }

    private function resolveAccessibleRoom(Request $request): ChatRoom
    {
        $request->validate([
            'room_id' => ['required', 'integer', 'exists:chat_rooms,id'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $room = ChatRoom::query()->findOrFail((int) $request->query('room_id'));

        if (! $room->isAccessibleBy($user)) {
            abort(403);
        }

        return $room;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(Message $message, ?int $currentUserId): array
    {
        $userName = $message->user?->name;

        return [
            'id' => $message->id,
            'chat_room_id' => $message->chat_room_id,
            'user_id' => $message->user_id,
            'user_name' => $userName,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'is_mine' => $currentUserId !== null && $message->user_id === $currentUserId,
            'is_system' => $userName === null,
            'reply_to' => $message->replyToPayload(),
            'attachments' => $message->attachments->map(
                fn (MessageAttachment $attachment) => $this->formatAttachment($attachment),
            )->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatAttachment(MessageAttachment $attachment): array
    {
        $attachment->ensureValidToken();

        return [
            'id' => $attachment->id,
            'url' => $attachment->url(),
            'mime_type' => $attachment->mime_type,
            'original_name' => $attachment->original_name,
            'message_id' => $attachment->message_id,
            'created_at' => $attachment->created_at?->toIso8601String(),
        ];
    }
}
