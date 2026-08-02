<?php

namespace Tests\Feature;

use App\Events\MessageLinkPreviewReady;
use App\Models\ChatRoom;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshLinkPreviewsCommandTest extends TestCase
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

    public function test_it_refreshes_preview_for_message_and_broadcasts(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);
        Http::fake([
            'https://example.com/post' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <meta property="og:title" content="Updated title">
                        <meta property="og:description" content="Updated description">
                    </head>
                </html>
                HTML, 200),
        ]);

        $message = $this->makeMessage('Read https://example.com/post');
        $preview = LinkPreview::query()->create([
            'url' => 'https://example.com/post',
            'title' => 'Old title',
            'description' => 'Old description',
        ]);
        $message->forceFill(['link_preview_id' => $preview->id])->save();

        $this->artisan('link-previews:refresh', [
            '--message' => $message->id,
            '--force' => true,
        ])->assertSuccessful();

        $preview->refresh();

        $this->assertSame('Updated title', $preview->title);
        $this->assertSame('Updated description', $preview->description);

        Event::assertDispatched(MessageLinkPreviewReady::class, function (MessageLinkPreviewReady $event) use ($message, $preview) {
            return $event->message->id === $message->id
                && $event->linkPreview->id === $preview->id;
        });
    }

    public function test_it_reuses_cached_preview_without_force(): void
    {
        Event::fake([MessageLinkPreviewReady::class]);
        Http::fake();

        $message = $this->makeMessage('Read https://example.com/cached');
        $preview = LinkPreview::query()->create([
            'url' => 'https://example.com/cached',
            'title' => 'Cached title',
        ]);
        $message->forceFill(['link_preview_id' => $preview->id])->save();

        $this->artisan('link-previews:refresh', [
            '--message' => $message->id,
        ])->assertSuccessful();

        Http::assertNothingSent();

        Event::assertDispatched(MessageLinkPreviewReady::class);
    }

    public function test_it_requires_message_or_force_flag(): void
    {
        $this->artisan('link-previews:refresh')
            ->assertFailed();
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
