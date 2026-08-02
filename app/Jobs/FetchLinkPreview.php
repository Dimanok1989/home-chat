<?php

namespace App\Jobs;

use App\Events\MessageLinkPreviewReady;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Support\LinkPreviewUrlGuard;
use App\Support\MessageUrlExtractor;
use App\Support\OgMetaParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchLinkPreview implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 20;

    public function __construct(
        public int $messageId,
    ) {}

    public function handle(): void
    {
        $message = Message::query()->with('attachments')->find($this->messageId);

        if ($message === null || $message->trashed()) {
            return;
        }

        if ($message->attachments->isNotEmpty()) {
            return;
        }

        $url = MessageUrlExtractor::singleUrl($message->body);

        if ($url === null || ! LinkPreviewUrlGuard::isAllowed($url)) {
            return;
        }

        $preview = LinkPreview::query()->where('url', $url)->first();

        if ($preview === null) {
            $preview = $this->fetchAndStore($url);

            if ($preview === null) {
                return;
            }
        }

        if ($message->link_preview_id !== $preview->id) {
            $message->forceFill([
                'link_preview_id' => $preview->id,
            ])->save();
        }

        event(new MessageLinkPreviewReady($message->fresh(), $preview));
    }

    private function fetchAndStore(string $url): ?LinkPreview
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; HomeChatLinkPreview/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->withOptions([
                    'allow_redirects' => false,
                ])
                ->get($url);
        } catch (ConnectionException $exception) {
            Log::warning('link_preview.fetch_failed', [
                'url' => $url,
                'message_id' => $this->messageId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        if (strlen($body) > 1_000_000) {
            $body = substr($body, 0, 1_000_000);
        }

        $meta = OgMetaParser::parse($body, $url);
        $imageUrl = $meta['image_url'];

        if ($imageUrl !== null && ! LinkPreviewUrlGuard::isAllowedImageUrl($imageUrl)) {
            $imageUrl = null;
        }

        if ($meta['title'] === null && $meta['description'] === null && $imageUrl === null) {
            return null;
        }

        try {
            return LinkPreview::query()->create([
                'url' => $url,
                'title' => $meta['title'],
                'description' => $meta['description'],
                'image_url' => $imageUrl,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueUrlViolation($exception)) {
                throw $exception;
            }

            return LinkPreview::query()->where('url', $url)->first() ?? throw $exception;
        }
    }

    private function isUniqueUrlViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? null;
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = strtolower($exception->getMessage());

        return $sqlState === '23000'
            || $sqlState === '23505'
            || $driverCode === 1062
            || ($driverCode === 19 && str_contains($message, 'unique constraint failed'));
    }
}
