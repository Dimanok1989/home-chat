<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $guestId = $request->session()->get('guest_id');
        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $beforeId = $request->query('before_id');

        $query = Message::query()
            ->with('attachments')
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
            ->map(fn (Message $message) => $this->formatMessage($message, $guestId));

        return response()->json([
            'guest_id' => $guestId,
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

        $guestId = $request->session()->get('guest_id');

        $message = DB::transaction(function () use ($request, $guestId, $body, $hasImage) {
            $message = Message::query()->create([
                'guest_id' => $guestId,
                'ip_address' => $request->ip(),
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

                $attachment = $message->attachments()->create([
                    'disk' => 'local',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType() ?? 'image/jpeg',
                    'size' => $file->getSize(),
                    'access_token' => Str::random(64),
                    'token_expires_at' => now()->addHours(24),
                ]);
            }

            return $message->load('attachments');
        });

        broadcast(new MessageSent($message));

        return response()->json([
            'message' => $this->formatMessage($message, $guestId),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatMessage(Message $message, ?string $guestId): array
    {
        return [
            'id' => $message->id,
            'guest_id' => $message->guest_id,
            'ip_address' => $message->ip_address,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
            'is_mine' => $guestId !== null && $message->guest_id === $guestId,
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
