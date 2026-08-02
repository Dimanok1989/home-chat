<?php

namespace App\Support;

use App\Models\LinkPreview;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LinkPreviewFetcher
{
    public function resolveForUrl(string $url, bool $force = false, ?int $messageId = null): ?LinkPreview
    {
        $existing = LinkPreview::query()->where('url', $url)->first();

        if ($existing !== null && ! $force) {
            return $existing;
        }

        if ($existing !== null && $force) {
            return $this->refreshExisting($existing, $url, $messageId);
        }

        return $this->fetchAndStore($url, $messageId);
    }

    public function fetchAndStore(string $url, ?int $messageId = null): ?LinkPreview
    {
        $data = $this->fetchOgData($url, $messageId);

        if ($data === null) {
            return null;
        }

        try {
            return LinkPreview::query()->create([
                'url' => $url,
                ...$data,
            ]);
        } catch (QueryException $exception) {
            if (! $this->isUniqueUrlViolation($exception)) {
                throw $exception;
            }

            return LinkPreview::query()->where('url', $url)->first() ?? throw $exception;
        }
    }

    public function refreshExisting(LinkPreview $preview, ?string $url = null, ?int $messageId = null): ?LinkPreview
    {
        $url ??= $preview->url;
        $data = $this->fetchOgData($url, $messageId);

        if ($data === null) {
            return null;
        }

        if ($preview->hasLocalImage() && isset($data['image_path']) && $data['image_path'] !== $preview->image_path) {
            $this->deleteLocalImage($preview);
        }

        $preview->fill($data);
        $preview->save();

        return $preview->fresh();
    }

    /**
     * @return array{title: string|null, description: string|null, image_url: string|null, image_disk?: string, image_path?: string, image_mime_type?: string, image_access_token?: string}|null
     */
    private function fetchOgData(string $url, ?int $messageId = null): ?array
    {
        try {
            $fetched = $this->fetchHtmlFollowingRedirects($url);
        } catch (ConnectionException $exception) {
            Log::warning('link_preview.fetch_failed', [
                'url' => $url,
                'message_id' => $messageId,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($fetched === null) {
            return null;
        }

        $meta = OgMetaParser::parse($fetched['body'], $fetched['pageUrl']);
        $remoteImageUrl = $meta['image_url'];

        if ($remoteImageUrl !== null && ! LinkPreviewUrlGuard::isAllowedImageUrl($remoteImageUrl)) {
            $remoteImageUrl = null;
        }

        $storedImage = $remoteImageUrl !== null
            ? $this->downloadAndStoreImage($remoteImageUrl, $messageId)
            : null;

        if ($meta['title'] === null && $meta['description'] === null && $remoteImageUrl === null) {
            return null;
        }

        return [
            'title' => $meta['title'],
            'description' => $meta['description'],
            'image_url' => $remoteImageUrl,
            ...$storedImage ?? [],
        ];
    }

    public function deleteLocalImage(LinkPreview $preview): void
    {
        if ($preview->image_path === null) {
            return;
        }

        $diskName = $preview->image_disk ?? 'local';
        $disk = Storage::disk($diskName);

        if ($disk->exists($preview->image_path)) {
            $disk->delete($preview->image_path);
        }
    }

    /**
     * @return array{body: string, pageUrl: string}|null
     */
    private function fetchHtmlFollowingRedirects(string $url): ?array
    {
        $currentUrl = $url;
        $maxRedirects = 3;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; HomeChatLinkPreview/1.0)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->withOptions([
                    'allow_redirects' => false,
                ])
                ->get($currentUrl);

            if ($response->redirect()) {
                if ($hop === $maxRedirects) {
                    return null;
                }

                $location = $response->header('Location');

                if (! is_string($location) || trim($location) === '') {
                    return null;
                }

                $nextUrl = $this->resolveRedirectUrl($currentUrl, $location);

                if ($nextUrl === null || ! LinkPreviewUrlGuard::isAllowed($nextUrl)) {
                    return null;
                }

                $currentUrl = $nextUrl;

                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();

            if (strlen($body) > 1_000_000) {
                $body = substr($body, 0, 1_000_000);
            }

            return [
                'body' => $body,
                'pageUrl' => $currentUrl,
            ];
        }

        return null;
    }

    private function resolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        $location = trim($location);

        if ($location === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        if (str_starts_with($location, '//')) {
            return 'https:'.$location;
        }

        $base = parse_url($baseUrl);

        if (! is_array($base) || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        $scheme = $base['scheme'];
        $host = $base['host'];
        $port = isset($base['port']) ? ':'.$base['port'] : '';

        if (str_starts_with($location, '/')) {
            return $scheme.'://'.$host.$port.$location;
        }

        $path = $base['path'] ?? '/';
        $dir = str_contains($path, '/')
            ? substr($path, 0, (int) strrpos($path, '/') + 1)
            : '/';

        return $scheme.'://'.$host.$port.$dir.$location;
    }

    /**
     * @return array{image_disk: string, image_path: string, image_mime_type: string, image_access_token: string}|null
     */
    private function downloadAndStoreImage(string $imageUrl, ?int $messageId = null): ?array
    {
        try {
            $response = $this->fetchBinaryFollowingRedirects($imageUrl);
        } catch (ConnectionException) {
            return null;
        }

        if ($response === null) {
            return null;
        }

        $body = $response['body'];
        $contentType = $response['contentType'];

        if ($body === '' || ! is_string($contentType)) {
            return null;
        }

        $mimeType = strtolower(trim(explode(';', $contentType)[0]));

        if (! str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $extension = match ($mimeType) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            default => 'jpg',
        };

        $path = 'chat/link-previews/'.Str::uuid().'.'.$extension;

        try {
            Storage::disk('local')->makeDirectory('chat/link-previews', 0775, true);

            if (! Storage::disk('local')->put($path, $body)) {
                Log::warning('link_preview.image_save_failed', [
                    'message_id' => $messageId,
                    'path' => $path,
                ]);

                return null;
            }
        } catch (\Throwable $e) {
            Log::warning('link_preview.image_save_failed', [
                'message_id' => $messageId,
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return [
            'image_disk' => 'local',
            'image_path' => $path,
            'image_mime_type' => $mimeType,
            'image_access_token' => Str::random(64),
        ];
    }

    /**
     * @return array{body: string, contentType: string|null}|null
     */
    private function fetchBinaryFollowingRedirects(string $url): ?array
    {
        $currentUrl = $url;
        $maxRedirects = 3;
        $maxBytes = 5_000_000;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; HomeChatLinkPreview/1.0)',
                    'Accept' => 'image/*',
                ])
                ->withOptions([
                    'allow_redirects' => false,
                ])
                ->get($currentUrl);

            if ($response->redirect()) {
                if ($hop === $maxRedirects) {
                    return null;
                }

                $location = $response->header('Location');

                if (! is_string($location) || trim($location) === '') {
                    return null;
                }

                $nextUrl = $this->resolveRedirectUrl($currentUrl, $location);

                if ($nextUrl === null || ! LinkPreviewUrlGuard::isAllowed($nextUrl)) {
                    return null;
                }

                $currentUrl = $nextUrl;

                continue;
            }

            if (! $response->successful()) {
                return null;
            }

            $body = $response->body();

            if (strlen($body) > $maxBytes) {
                return null;
            }

            return [
                'body' => $body,
                'contentType' => $response->header('Content-Type'),
            ];
        }

        return null;
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
