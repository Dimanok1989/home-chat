<?php

namespace App\Events;

use App\Models\LinkPreview;
use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageLinkPreviewReady implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Message $message,
        public LinkPreview $linkPreview,
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
        return 'MessageLinkPreviewReady';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message_id' => $this->message->id,
            'chat_room_id' => $this->message->chat_room_id,
            'link_preview' => $this->linkPreview->toApiArray(),
        ];
    }
}
