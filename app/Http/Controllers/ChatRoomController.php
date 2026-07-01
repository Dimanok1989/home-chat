<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChatRoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $rooms = ChatRoom::query()
            ->forUser($user->id)
            ->with([
                'users',
                'messages' => fn ($query) => $query
                    ->with(['attachments', 'user'])
                    ->latest('id')
                    ->limit(1),
            ])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'rooms' => $rooms->map(fn (ChatRoom $room) => $this->formatRoom($room, $user))->values()->all(),
        ]);
    }

    public function storeDirect(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $targetUserId = (int) $validated['user_id'];

        if ($targetUserId === $user->id) {
            throw ValidationException::withMessages([
                'user_id' => ['Нельзя создать диалог с самим собой.'],
            ]);
        }

        $room = ChatRoom::findOrCreateDirect($user->id, $targetUserId);
        $room->load([
            'users',
            'messages' => fn ($query) => $query
                ->with(['attachments', 'user'])
                ->latest('id')
                ->limit(1),
        ]);

        return response()->json([
            'room' => $this->formatRoom($room, $user),
        ], 201);
    }

    public function storeGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id', 'distinct'],
        ]);

        /** @var User $user */
        $user = Auth::user();

        $memberIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === $user->id)
            ->values()
            ->all();

        if ($memberIds === []) {
            throw ValidationException::withMessages([
                'user_ids' => ['Добавьте хотя бы одного участника кроме себя.'],
            ]);
        }

        $room = ChatRoom::createGroup($validated['name'], $memberIds, $user->id);
        $room->load([
            'users',
            'messages' => fn ($query) => $query
                ->with(['attachments', 'user'])
                ->latest('id')
                ->limit(1),
        ]);

        return response()->json([
            'room' => $this->formatRoom($room, $user),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRoom(ChatRoom $room, User $user): array
    {
        $lastMessage = $room->messages->first();

        return [
            'id' => $room->id,
            'type' => $room->type,
            'title' => $room->titleFor($user),
            'last_message' => $lastMessage ? Message::previewPayload($lastMessage) : null,
            'last_message_at' => $room->last_message_at?->toIso8601String(),
            'created_at' => $room->created_at?->toIso8601String(),
        ];
    }
}
