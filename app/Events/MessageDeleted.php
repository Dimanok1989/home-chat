<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{preview: string, user_name: string|null, created_at: string|null}|null  $lastMessage
     */
    public function __construct(
        public int $messageId,
        public int $chatRoomId,
        public ?array $lastMessage,
        public string $lastMessageAt,
    ) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.room.'.$this->chatRoomId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageDeleted';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
            'chat_room_id' => $this->chatRoomId,
            'last_message' => $this->lastMessage,
            'last_message_at' => $this->lastMessageAt,
        ];
    }
}
