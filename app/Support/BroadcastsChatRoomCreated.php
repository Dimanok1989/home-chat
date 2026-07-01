<?php

namespace App\Support;

use App\Events\ChatRoomCreated;
use App\Models\ChatRoom;

trait BroadcastsChatRoomCreated
{
    protected function broadcastChatRoomCreated(ChatRoom $room, ?int $exceptUserId = null): void
    {
        $room->loadMissing(['users']);

        foreach ($room->users as $member) {
            if ($exceptUserId !== null && $member->id === $exceptUserId) {
                continue;
            }

            broadcast(new ChatRoomCreated($room, $member));
        }
    }
}
