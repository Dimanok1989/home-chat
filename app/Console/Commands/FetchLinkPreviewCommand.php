<?php

namespace App\Console\Commands;

use App\Jobs\FetchLinkPreview;
use App\Models\LinkPreview;
use App\Models\Message;
use App\Support\LinkPreviewUrlGuard;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('link-previews:fetch
    {url : URL для обновления OG-превью}
    {--force : Принудительно перезагрузить OG-данные с сайта}
    {--message= : ID сообщения для отправки broadcast; без параметра событие не отправляется}')]
#[Description('Принудительно обновить OG-превью ссылки через FetchLinkPreview job')]
class FetchLinkPreviewCommand extends Command
{
    public function handle(): int
    {
        $url = (string) $this->argument('url');
        $force = (bool) $this->option('force');
        $broadcastMessageOption = $this->option('message');
        $shouldBroadcast = $broadcastMessageOption !== null;
        $broadcastMessageId = $shouldBroadcast ? (int) $broadcastMessageOption : null;

        if (! LinkPreviewUrlGuard::isAllowed($url)) {
            $this->error("URL не разрешён: {$url}");

            return self::FAILURE;
        }

        if ($shouldBroadcast) {
            $broadcastMessage = Message::query()->find($broadcastMessageId);

            if ($broadcastMessage === null || $broadcastMessage->trashed()) {
                $this->error("Сообщение #{$broadcastMessageId} не найдено.");

                return self::FAILURE;
            }
        }

        FetchLinkPreview::dispatchSync(
            force: $force,
            broadcastMessageId: $broadcastMessageId,
            shouldBroadcast: $shouldBroadcast,
            url: $url,
        );

        $preview = LinkPreview::query()->where('url', $url)->first();

        if ($preview === null) {
            $this->error('Не удалось получить OG-превью для URL.');

            return self::FAILURE;
        }

        $this->info("Превью обновлено (preview #{$preview->id}).");
        $this->line("URL: {$preview->url}");

        if ($shouldBroadcast) {
            $this->line("Broadcast отправлен для сообщения #{$broadcastMessageId}.");
        }

        return self::SUCCESS;
    }
}
