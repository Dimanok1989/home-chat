<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class CallSignal implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public int $targetUserId,
        public array $data,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat.user.'.$this->targetUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'CallSignal';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->data;
    }
}