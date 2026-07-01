<?php

use App\Models\ChatRoom;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['web', 'auth']]);

Broadcast::channel('chat.user.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('chat.room.{roomId}', function ($user, $roomId) {
    $room = ChatRoom::query()->find($roomId);

    if ($room === null || ! $room->isAccessibleBy($user)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
    ];
});
