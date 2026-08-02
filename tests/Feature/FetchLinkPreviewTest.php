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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchLinkPreviewTest extends TestCase
{
    use RefreshDatabase;

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
        ]);

        $message = $this->makeMessage('See https://example.com/article');

        self::assertTrue(class_exists(FetchLinkPreview::class), 'FetchLinkPreview job must exist.');

        (new FetchLinkPreview($message->id))->handle();

        $message->refresh();

        $this->assertNotNull($message->link_preview_id);
        $this->assertDatabaseHas('link_previews', [
            'id' => $message->link_preview_id,
            'url' => 'https://example.com/article',
            'title' => 'Example title',
            'description' => 'Example description',
            'image_url' => 'https://cdn.example.com/image.jpg',
        ]);
        Event::assertDispatched(MessageLinkPreviewReady::class, function (MessageLinkPreviewReady $event) use ($message): bool {
            return $event->message->id === $message->id
                && $event->message->chat_room_id === $message->chat_room_id
                && $event->linkPreview->url === 'https://example.com/article';
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
        ]);

        self::assertTrue(class_exists(FetchLinkPreview::class), 'FetchLinkPreview job must exist.');

        (new FetchLinkPreview($message->id))->handle();

        $message->refresh();

        $this->assertSame($preview->id, $message->link_preview_id);
        Http::assertNothingSent();
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
