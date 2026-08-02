<?php

namespace Tests\Feature;

use App\Events\MessageLinkPreviewReady;
use App\Jobs\FetchLinkPreview;
use App\Models\ChatRoom;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
