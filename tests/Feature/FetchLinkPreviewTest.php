<?php

namespace Tests\Feature;

use App\Events\MessageLinkPreviewReady;
use App\Jobs\FetchLinkPreview;
use App\Models\ChatRoom;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchLinkPreviewTest extends TestCase
{
    private ?string $tempStorageRoot = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempStorageRoot = sys_get_temp_dir().'/homechat-test-'.getmypid();
        mkdir($this->tempStorageRoot, 0777, true);
        config(['filesystems.disks.local.root' => $this->tempStorageRoot]);
    }

    protected function tearDown(): void
    {
        if ($this->tempStorageRoot !== null && is_dir($this->tempStorageRoot)) {
            $this->deleteDirectory($this->tempStorageRoot);
        }

        parent::tearDown();
    }

    private function deleteDirectory(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir.'/'.$entry;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    public function test_it_fetches_and_attaches_a_link_preview(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);
        Http::fake([
            'https://example.com/article' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Example title">
                        <meta property="og:description" content="Example description">
                        <meta property="og:image" content="https://cdn.example.com/image.jpg">
                    </head>
                </html>
                HTML, 200),
            'https://cdn.example.com/image.jpg' => Http::response('jpeg-bytes', 200, [
                'Content-Type' => 'image/jpeg',
            ]),
        ]);

        $message = $this->makeMessage('See https://example.com/article');

        self::assertTrue(class_exists(FetchLinkPreview::class), 'FetchLinkPreview job must exist.');

        (new FetchLinkPreview($message->id))->handle();

        $message->refresh();
        $preview = LinkPreview::query()->find($message->link_preview_id);

        $this->assertNotNull($message->link_preview_id);
        $this->assertDatabaseHas('link_previews', [
            'id' => $message->link_preview_id,
            'url' => 'https://example.com/article',
            'title' => 'Example title',
            'description' => 'Example description',
            'image_url' => 'https://cdn.example.com/image.jpg',
        ]);
        $this->assertNotNull($preview?->image_path);
        $this->assertSame('local', $preview?->image_disk);
        $this->assertSame('image/jpeg', $preview?->image_mime_type);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($preview->image_path));
        $this->assertStringContainsString('/api/chat/link-previews/', $preview->toApiArray()['image_url'] ?? '');
        Event::assertDispatched(MessageLinkPreviewReady::class, function (MessageLinkPreviewReady $event) use ($message): bool {
            return $event->message->id === $message->id
                && $event->message->chat_room_id === $message->chat_room_id
                && $event->linkPreview->url === 'https://example.com/article'
                && str_contains($event->linkPreview->toApiArray()['image_url'] ?? '', '/api/chat/link-previews/');
        });
    }

    public function test_it_skips_messages_with_multiple_urls(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);
        Http::fake();

        $message = $this->makeMessage('Links: https://example.com/a and https://example.com/b');

        self::assertTrue(class_exists(FetchLinkPreview::class), 'FetchLinkPreview job must exist.');

        (new FetchLinkPreview($message->id))->handle();

        $message->refresh();

        $this->assertNull($message->link_preview_id);
        $this->assertDatabaseCount('link_previews', 0);
        Http::assertNothingSent();
        Event::assertNotDispatched(MessageLinkPreviewReady::class);
    }

    public function test_it_reuses_a_cached_preview_without_fetching_again(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);
        Http::fake();

        $message = $this->makeMessage('Cached https://example.com/cached');
        $preview = LinkPreview::query()->create([
            'url' => 'https://example.com/cached',
            'title' => 'Cached title',
            'description' => 'Cached description',
            'image_url' => 'https://cdn.example.com/cached.jpg',
            'image_disk' => 'local',
            'image_path' => 'chat/link-previews/cached.jpg',
            'image_mime_type' => 'image/jpeg',
            'image_access_token' => str_repeat('b', 64),
        ]);
        \Illuminate\Support\Facades\Storage::disk('local')->put('chat/link-previews/cached.jpg', 'cached-image');

        self::assertTrue(class_exists(FetchLinkPreview::class), 'FetchLinkPreview job must exist.');

        (new FetchLinkPreview($message->id))->handle();

        $message->refresh();

        $this->assertSame($preview->id, $message->link_preview_id);
        Http::assertNothingSent();
        $this->assertStringContainsString('/api/chat/link-previews/', $preview->fresh()->toApiArray()['image_url'] ?? '');
        Event::assertDispatched(MessageLinkPreviewReady::class, function (MessageLinkPreviewReady $event) use ($message, $preview): bool {
            return $event->message->id === $message->id
                && $event->linkPreview->is($preview);
        });
    }

    public function test_it_recovers_when_preview_is_created_during_fetch_race(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);
        Http::fake([
            'https://example.com/race' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Fresh title">
                        <meta property="og:description" content="Fresh description">
                    </head>
                </html>
                HTML, 200),
        ]);

        $message = $this->makeMessage('Race https://example.com/race');

        $seeded = false;

        DB::listen(function (QueryExecuted $query) use (&$seeded): void {
            if ($seeded) {
                return;
            }

            if (! str_contains($query->sql, 'from "link_previews"') || ! str_contains($query->sql, 'where "url" = ?')) {
                return;
            }

            if (($query->bindings[0] ?? null) !== 'https://example.com/race') {
                return;
            }

            $seeded = true;

            LinkPreview::query()->create([
                'url' => 'https://example.com/race',
                'title' => 'Seeded title',
                'description' => 'Seeded description',
                'image_url' => null,
            ]);
        });

        (new FetchLinkPreview($message->id))->handle();

        $message->refresh();
        $preview = LinkPreview::query()->where('url', 'https://example.com/race')->first();

        $this->assertTrue($seeded);
        $this->assertNotNull($preview);
        $this->assertSame($preview->id, $message->link_preview_id);
        $this->assertSame(1, LinkPreview::query()->where('url', 'https://example.com/race')->count());
        Event::assertDispatched(MessageLinkPreviewReady::class, function (MessageLinkPreviewReady $event) use ($message, $preview): bool {
            return $event->message->id === $message->id
                && $event->linkPreview->is($preview);
        });
    }

    public function test_it_skips_messages_with_attachments(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);
        Http::fake();

        $message = $this->makeMessage('See https://example.com/attachment');
        MessageAttachment::query()->create([
            'message_id' => $message->id,
            'disk' => 'local',
            'path' => 'attachments/test.png',
            'original_name' => 'test.png',
            'mime_type' => 'image/png',
            'size' => 123,
            'access_token' => str_repeat('a', 64),
            'token_expires_at' => now()->addHour(),
        ]);

        (new FetchLinkPreview($message->id))->handle();

        $message->refresh();

        $this->assertNull($message->link_preview_id);
        $this->assertDatabaseCount('link_previews', 0);
        Http::assertNothingSent();
        Event::assertNotDispatched(MessageLinkPreviewReady::class);
    }

    public function test_it_follows_redirects_and_parses_og_from_final_200_response(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);
        Http::fake([
            'https://example.com/short' => Http::response('', 302, ['Location' => 'https://example.com/final']),
            'https://example.com/final' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Final title">
                        <meta property="og:description" content="Final description">
                        <meta property="og:image" content="/img/preview.jpg">
                    </head>
                </html>
                HTML, 200),
            'https://example.com/img/preview.jpg' => Http::response('png-bytes', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $message = $this->makeMessage('See https://example.com/short');

        (new FetchLinkPreview($message->id))->handle();

        $message->refresh();
        $preview = LinkPreview::query()->find($message->link_preview_id);

        $this->assertNotNull($message->link_preview_id);
        $this->assertDatabaseHas('link_previews', [
            'url' => 'https://example.com/short',
            'title' => 'Final title',
            'description' => 'Final description',
            'image_url' => 'https://example.com/img/preview.jpg',
        ]);
        $this->assertNotNull($preview?->image_path);
        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($preview->image_path));
        Http::assertSentCount(3);
        Event::assertDispatched(MessageLinkPreviewReady::class);
    }

    public function test_it_rejects_redirect_to_disallowed_host(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);
        Http::fake([
            'https://example.com/redirect' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret']),
        ]);

        $message = $this->makeMessage('See https://example.com/redirect');

        (new FetchLinkPreview($message->id))->handle();

        $message->refresh();

        $this->assertNull($message->link_preview_id);
        $this->assertDatabaseCount('link_previews', 0);
        Http::assertSentCount(1);
        Event::assertNotDispatched(MessageLinkPreviewReady::class);
    }

    public function test_it_falls_back_to_source_image_url_when_local_file_is_missing(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);

        $preview = LinkPreview::query()->create([
            'url' => 'https://example.com/fallback',
            'title' => 'Fallback title',
            'description' => null,
            'image_url' => 'https://cdn.example.com/source.jpg',
            'image_disk' => 'local',
            'image_path' => 'chat/link-previews/missing.jpg',
            'image_mime_type' => 'image/jpeg',
            'image_access_token' => str_repeat('c', 64),
        ]);

        $this->assertSame('https://cdn.example.com/source.jpg', $preview->displayImageUrl());
    }

    public function test_it_uses_source_image_url_when_download_fails(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);
        Http::fake([
            'https://example.com/page' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Page title">
                        <meta property="og:image" content="https://cdn.example.com/broken.jpg">
                    </head>
                </html>
                HTML, 200),
            'https://cdn.example.com/broken.jpg' => Http::response('', 500),
        ]);

        $message = $this->makeMessage('See https://example.com/page');

        (new FetchLinkPreview($message->id))->handle();

        $message->refresh();
        $preview = LinkPreview::query()->find($message->link_preview_id);

        $this->assertNotNull($preview);
        $this->assertSame('https://cdn.example.com/broken.jpg', $preview->image_url);
        $this->assertNull($preview->image_path);
        $this->assertSame('https://cdn.example.com/broken.jpg', $preview->displayImageUrl());
    }

    private function makeMessage(string $body): Message
    {
        $user = User::factory()->create();
        $room = ChatRoom::query()->create([
            'type' => ChatRoom::TYPE_GROUP,
            'name' => 'Test room',
        ]);
        $room->users()->attach($user);

        return Message::query()->create([
            'user_id' => $user->id,
            'chat_room_id' => $room->id,
            'body' => $body,
        ]);
    }
}
