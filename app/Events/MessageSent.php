<?php

namespace App\Events;

use App\Models\Message;
use App\Models\MessageAttachment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
    ) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.room.'.$this->message->chat_room_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'MessageSent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->loadMissing(['attachments', 'user']);

        return [
            'id' => $this->message->id,
            'chat_room_id' => $this->message->chat_room_id,
            'user_id' => $this->message->user_id,
            'user_name' => $this->message->user?->name,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toIso8601String(),
            'is_system' => $this->message->user?->name === null,
            'attachments' => $this->message->attachments->map(
                fn (MessageAttachment $attachment) => [
                    'id' => $attachment->id,
                    'url' => $attachment->url(),
                    'mime_type' => $attachment->mime_type,
                    'original_name' => $attachment->original_name,
                    'message_id' => $attachment->message_id,
                    'created_at' => $attachment->created_at?->toIso8601String(),
                ],
            )->values()->all(),
        ];
    }
}
