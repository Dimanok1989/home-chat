<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\MessageAttachment;
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
        $currentUserId = Auth::id();
        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $beforeId = $request->query('before_id');

        $query = Message::query()
            ->with(['attachments', 'user'])
            ->orderByDesc('id');

        if ($beforeId !== null && $beforeId !== '') {
            $query->where('id', '<', (int) $beforeId);
        }

        $messages = $query
            ->limit($limit + 1)
            ->get();

        $hasMore = $messages->count() > $limit;

        if ($hasMore) {
            $messages = $messages->take($limit);
        }

        $messages = $messages
            ->reverse()
            ->values()
            ->map(fn (Message $message) => $this->formatMessage($message, $currentUserId));

        return response()->json([
            'messages' => $messages,
            'has_more' => $hasMore,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $body = isset($validated['body']) ? trim($validated['body']) : '';
        $hasImage = $request->hasFile('image');

        if ($body === '' && ! $hasImage) {
            throw ValidationException::withMessages([
                'body' => ['Укажите текст сообщения или прикрепите изображение.'],
            ]);
        }

        $currentUserId = Auth::id();

        $message = DB::transaction(function () use ($body, $hasImage, $request, $currentUserId) {
            $message = Message::query()->create([
                'user_id' => $currentUserId,
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

            return $message->load(['attachments', 'user']);
        });

        broadcast(new MessageSent($message));

        return response()->json([
            'message' => $this->formatMessage($message, $currentUserId),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(Message $message, ?int $currentUserId): array
    {
        $userName = $message->user?->name;

        return [
            'id' => $message->id,
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
