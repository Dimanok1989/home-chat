<?php

namespace App\Events;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProfileUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
    ) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        $roomIds = $this->user->chatRooms()->pluck('chat_rooms.id');

        ChatRoom::query()
            ->global()
            ->pluck('id')
            ->each(fn (int $id) => $roomIds->push($id));

        return $roomIds
            ->unique()
            ->values()
            ->map(fn (int $id) => new PresenceChannel('chat.room.'.$id))
            ->all();
    }

    public function broadcastAs(): string
    {
        return 'ProfileUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'display_name' => $this->user->displayName(),
            'has_avatar' => (bool) $this->user->avatar_path,
            'avatar_url' => $this->user->avatarUrl(),
            'initial' => $this->user->initial(),
        ];
    }
}
