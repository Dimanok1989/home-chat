<?php

namespace App\Http\Controllers;

use App\Events\CallSignal;
use App\Events\MessageSent;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\User;
use App\Support\BroadcastsChatRoomCreated;
use App\Support\BroadcastsUnreadCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CallController extends Controller
{
    use BroadcastsChatRoomCreated;
    use BroadcastsUnreadCount;

    public function signal(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_user_id' => 'required|integer|exists:users,id',
            'type' => 'required|string|in:offer,answer,ice-candidate,hangup,camera-state',
            'payload' => 'required|array',
        ]);

        $callerId = (int) $request->user()->id;
        $targetUserId = (int) $data['target_user_id'];
        $type = $data['type'];

        \Illuminate\Support\Facades\Log::debug('[CallController] signal', [
            'caller' => $callerId,
            'target' => $targetUserId,
            'type' => $type,
            'payload_keys' => array_keys($data['payload']),
            'has_sdp' => isset($data['payload']['sdp']),
            'sdp_length' => isset($data['payload']['sdp']) ? strlen($data['payload']['sdp']) : null,
        ]);

        $targetUser = User::query()->findOrFail($targetUserId);

        $payload = array_merge($data['payload'], [
            'type' => $type,
            'caller_user_id' => $callerId,
        ]);

        broadcast(new CallSignal((int) $targetUser->id, $payload));

        return response()->json(['status' => 'ok']);
    }

    public function history(Request $request): JsonResponse
    {
        $data = $request->validate([
            'room_id' => 'required|integer|exists:chat_rooms,id',
            'caller_user_id' => 'required|integer|exists:users,id',
            'duration' => 'nullable|integer|min:0',
            'was_answered' => 'required|boolean',
        ]);

        /** @var User $user */
        $user = $request->user();
        $room = ChatRoom::query()->findOrFail((int) $data['room_id']);

        \Illuminate\Support\Facades\Log::debug('[CallController] history', [
            'user' => $user->id,
            'room' => $room->id,
            'caller_user_id' => $data['caller_user_id'],
            'duration' => $data['duration'],
            'was_answered' => $data['was_answered'],
        ]);

        if (! $room->isAccessibleBy($user)) {
            abort(403);
        }

        $callerUserId = (int) $data['caller_user_id'];
        $duration = (int) ($data['duration'] ?? 0);
        $wasAnswered = (bool) $data['was_answered'];

        // Build the call body with a structured format.
        // The frontend detects call messages by the 📞 prefix and renders
        // the appropriate text/icon based on is_mine (whether the current
        // user is the caller).
        if ($wasAnswered) {
            $minutes = intdiv($duration, 60);
            $seconds = $duration % 60;
            $durationStr = $minutes > 0
                ? sprintf('%d:%02d', $minutes, $seconds)
                : sprintf('0:%02d', $seconds);

            $body = sprintf("\u{1F4DE} %s", $durationStr);
        } else {
            // If the current user is the caller, they cancelled before answer.
            // If the current user is the callee, they rejected the call.
            $isCaller = $callerUserId === (int) $user->id;
            $body = $isCaller
                ? "\u{1F4DE} Звонок был отменен"
                : "\u{1F4DE} Звонок был отклонен";
        }

        $wasFirstMessage = $room->last_message_at === null;

        $message = DB::transaction(function () use ($room, $body, $callerUserId) {
            $message = Message::query()->create([
                'user_id' => $callerUserId,
                'chat_room_id' => $room->id,
                'reply_to_id' => null,
                'body' => $body,
            ]);

            $room->update(['last_message_at' => $message->created_at]);

            return $message->load(['attachments', 'user', 'replyTo.user', 'replyTo.attachments']);
        });

        broadcast(new MessageSent($message));

        $room->markReadUpTo($user->id, $message->id);
        $this->broadcastUnreadCountsForRoom($room->fresh(['users']));

        if ($wasFirstMessage) {
            $this->broadcastChatRoomCreated($room, $user->id);
        }

        return response()->json([
            'message' => [
                'id' => $message->id,
                'chat_room_id' => $message->chat_room_id,
                'user_id' => $callerUserId,
                'user_name' => $message->user?->name,
                'user_avatar_url' => $message->user?->avatarUrl(),
                'user_initial' => $message->user?->initial(),
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
                'is_mine' => $callerUserId === (int) $user->id,
                'is_system' => false,
                'reply_to' => null,
                'attachments' => [],
            ],
        ]);
    }
}