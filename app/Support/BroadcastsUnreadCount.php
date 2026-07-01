<?php

namespace App\Support;

use App\Events\UnreadCountUpdated;
use App\Models\ChatRoom;
use App\Models\User;

trait BroadcastsUnreadCount
{
    protected function broadcastUnreadCountForUser(ChatRoom $room, User $user): void
    {
        broadcast(new UnreadCountUpdated(
            $room,
            $user,
            $room->unreadCountFor($user->id),
        ));
    }

    protected function broadcastUnreadCountsForRoom(ChatRoom $room, ?int $exceptUserId = null): void
    {
        if ($room->type === ChatRoom::TYPE_GLOBAL) {
            User::query()->select(['id'])->orderBy('id')->chunkById(100, function ($users) use ($room, $exceptUserId) {
                foreach ($users as $user) {
                    if ($exceptUserId !== null && $user->id === $exceptUserId) {
                        continue;
                    }

                    $this->broadcastUnreadCountForUser($room, $user);
                }
            });

            return;
        }

        $room->loadMissing('users');

        foreach ($room->users as $member) {
            if ($exceptUserId !== null && $member->id === $exceptUserId) {
                continue;
            }

            $this->broadcastUnreadCountForUser($room, $member);
        }
    }
}
