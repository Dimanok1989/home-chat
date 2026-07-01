<?php

namespace App\Http\Controllers;

use App\Events\MessageDeleted;
use App\Events\MessageSent;
use App\Models\ChatRoom;
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
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();
        $room = $this->resolveAccessibleRoom($request);

        $currentUserId = $user->id;
        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $beforeId = $request->query('before_id');

        $query = Message::query()
            ->where('chat_room_id', $room->id);

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
            ->with(['attachments', 'user'])
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message) => $this->formatMessage($message, $currentUserId));

        return response()->json([
            'messages' => $messages,
            'has_more' => $hasMore,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'room_id' => ['required', 'integer', 'exists:chat_rooms,id'],
            'body' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $room = ChatRoom::query()->findOrFail((int) $validated['room_id']);

        if (! $room->isAccessibleBy($user)) {
            abort(403);
        }

        $body = isset($validated['body']) ? trim($validated['body']) : '';
        $hasImage = $request->hasFile('image');

        if ($body === '' && ! $hasImage) {
            throw ValidationException::withMessages([
                'body' => ['Укажите текст сообщения или прикрепите изображение.'],
            ]);
        }

        $currentUserId = $user->id;

        $message = DB::transaction(function () use ($body, $hasImage, $request, $currentUserId, $room) {
            $message = Message::query()->create([
                'user_id' => $currentUserId,
                'chat_room_id' => $room->id,
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

            return $message->load(['attachments', 'user']);
        });

        broadcast(new MessageSent($message));

        return response()->json([
            'message' => $this->formatMessage($message, $currentUserId),
        ], 201);
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
