<?php

namespace App\Console\Commands;

use App\Events\MessageLinkPreviewReady;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Support\LinkPreviewFetcher;
use App\Support\LinkPreviewUrlGuard;
use App\Support\MessageUrlExtractor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('link-previews:refresh
    {--message= : ID сообщения — обновить превью и отправить broadcast}
    {--force : Принудительно перезагрузить OG-данные с сайта}')]
#[Description('Обновить OG-превью ссылок в сообщениях')]
class RefreshLinkPreviewsCommand extends Command
{
    public function handle(LinkPreviewFetcher $fetcher): int
    {
        $messageId = $this->option('message');
        $force = (bool) $this->option('force');

        if ($messageId !== null) {
            return $this->refreshForMessage($fetcher, (int) $messageId, $force);
        }

        if (! $force) {
            $this->error('Укажите --message=ID или добавьте --force для обновления всех кэшированных превью.');

            return self::FAILURE;
        }

        return $this->refreshAll($fetcher);
    }

    private function refreshForMessage(LinkPreviewFetcher $fetcher, int $messageId, bool $force): int
    {
        $message = Message::query()->with('attachments')->find($messageId);

        if ($message === null || $message->trashed()) {
            $this->error("Сообщение #{$messageId} не найдено.");

            return self::FAILURE;
        }

        if ($message->attachments->isNotEmpty()) {
            $this->error('Сообщение содержит вложения — превью ссылки не поддерживается.');

            return self::FAILURE;
        }

        $url = MessageUrlExtractor::singleUrl($message->body);

        if ($url === null) {
            $this->error('В сообщении нет одной допустимой ссылки.');

            return self::FAILURE;
        }

        if (! LinkPreviewUrlGuard::isAllowed($url)) {
            $this->error("URL не разрешён: {$url}");

            return self::FAILURE;
        }

        $preview = $fetcher->resolveForUrl($url, $force, $messageId);

        if ($preview === null) {
            $this->error('Не удалось получить OG-превью.');

            return self::FAILURE;
        }

        if ($message->link_preview_id !== $preview->id) {
            $message->forceFill([
                'link_preview_id' => $preview->id,
            ])->save();
        }

        $message = $message->fresh();
        $preview = $preview->fresh();

        broadcast(new MessageLinkPreviewReady($message, $preview));

        $this->info("Превью обновлено для сообщения #{$messageId} (preview #{$preview->id}).");
        $this->line("Broadcast отправлен в chat.room.{$message->chat_room_id}.");

        return self::SUCCESS;
    }

    private function refreshAll(LinkPreviewFetcher $fetcher): int
    {
        $previews = LinkPreview::query()->orderBy('id')->get();

        if ($previews->isEmpty()) {
            $this->warn('Кэшированных превью нет.');

            return self::SUCCESS;
        }

        $updated = 0;
        $failed = 0;

        foreach ($previews as $preview) {
            $refreshed = $fetcher->refreshExisting($preview);

            if ($refreshed === null) {
                $this->warn("Не удалось обновить preview #{$preview->id} ({$preview->url}).");
                $failed++;

                continue;
            }

            $this->line("Обновлено preview #{$refreshed->id}: {$refreshed->url}");
            $updated++;
        }

        $this->info("Готово: обновлено {$updated}, ошибок {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
