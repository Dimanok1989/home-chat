<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
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
            ->visibleForUser($user->id)
            ->with(['users'])
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'rooms' => $rooms->map(fn (ChatRoom $room) => $room->toApiArray($user))->values()->all(),
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

        $hash = ChatRoom::directHash($user->id, $targetUserId);
        $room = ChatRoom::query()->where('direct_hash', $hash)->first();

        if ($room === null || $room->last_message_at === null) {
            return response()->json([
                'message' => 'Диалог не найден.',
            ], 404);
        }

        if (! $room->isAccessibleBy($user)) {
            $room->users()->syncWithoutDetaching([$user->id, $targetUserId]);
        }

        if (! $room->isAccessibleBy($user)) {
            abort(403, 'Нет доступа к этому чату.');
        }

        $room->load([
            'users',
            'messages' => fn ($query) => $query
                ->with(['attachments', 'user'])
                ->latest('id')
                ->limit(1),
        ]);

        return response()->json([
            'room' => $room->toApiArray($user),
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
            'room' => $room->toApiArray($user),
        ], 201);
    }

    public function destroy(ChatRoom $room): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        if ($room->type !== ChatRoom::TYPE_DIRECT) {
            abort(403, 'Можно удалить только личную беседу.');
        }

        if (! $room->isAccessibleBy($user)) {
            abort(403);
        }

        $room->users()->updateExistingPivot($user->id, [
            'cleared_at' => now(),
        ]);

        return response()->json([
            'id' => $room->id,
        ]);
    }
}
