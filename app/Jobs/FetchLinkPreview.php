<?php

namespace App\Jobs;

use App\Events\MessageLinkPreviewReady;
use App\Models\Message;
use App\Support\LinkPreviewFetcher;
use App\Support\LinkPreviewUrlGuard;
use App\Support\MessageUrlExtractor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class FetchLinkPreview implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public function __construct(
        public ?int $messageId = null,
        public bool $force = false,
        public ?int $broadcastMessageId = null,
        public bool $shouldBroadcast = true,
        public ?string $url = null,
    ) {}

    public function handle(LinkPreviewFetcher $fetcher): void
    {
        $url = $this->url;
        $message = null;

        if ($this->messageId !== null) {
            $message = Message::query()->with('attachments')->find($this->messageId);

            if ($message === null || $message->trashed()) {
                return;
            }

            if ($message->attachments->isNotEmpty()) {
                return;
            }

            $url = MessageUrlExtractor::singleUrl($message->body);
        }

        if ($url === null || ! LinkPreviewUrlGuard::isAllowed($url)) {
            return;
        }

        $contextMessageId = $this->messageId ?? $this->broadcastMessageId;

        $preview = $fetcher->resolveForUrl($url, force: $this->force, messageId: $contextMessageId);

        if ($preview === null) {
            return;
        }

        if ($message !== null && $message->link_preview_id !== $preview->id) {
            $message->forceFill([
                'link_preview_id' => $preview->id,
            ])->save();
        }

        if (! $this->shouldBroadcast) {
            return;
        }

        $broadcastMessageId = $this->broadcastMessageId ?? $this->messageId;

        if ($broadcastMessageId === null) {
            return;
        }

        $broadcastMessage = Message::query()->find($broadcastMessageId);

        if ($broadcastMessage === null || $broadcastMessage->trashed()) {
            return;
        }

        if ($broadcastMessage->link_preview_id !== $preview->id) {
            $broadcastMessage->forceFill([
                'link_preview_id' => $preview->id,
            ])->save();
        }

        broadcast(new MessageLinkPreviewReady(
            $broadcastMessage->fresh(),
            $preview->fresh(),
        ));
    }
}
