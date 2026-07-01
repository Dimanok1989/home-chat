<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'chat_room_id',
        'reply_to_id',
        'body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MessageAttachment::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id')->withTrashed();
    }

    /**
     * @return array{id: int, user_id: int|null, user_name: string|null, preview: string, deleted: bool}|null
     */
    public function replyToPayload(): ?array
    {
        if ($this->reply_to_id === null) {
            return null;
        }

        $this->loadMissing(['replyTo.user', 'replyTo.attachments']);

        $reply = $this->replyTo;

        if ($reply === null || $reply->trashed()) {
            return [
                'id' => $this->reply_to_id,
                'user_id' => null,
                'user_name' => null,
                'preview' => 'Сообщение удалено',
                'deleted' => true,
            ];
        }

        $preview = self::previewPayload($reply);

        return [
            'id' => $reply->id,
            'user_id' => $reply->user_id,
            'user_name' => $preview['user_name'],
            'preview' => $preview['preview'],
            'deleted' => false,
        ];
    }

    /**
     * @return array{preview: string, user_id: int|null, user_name: string|null, created_at: string|null}
     */
    public static function previewPayload(self $message): array
    {
        $message->loadMissing(['attachments', 'user']);

        $hasAttachments = $message->attachments->isNotEmpty();
        $body = $message->body;

        if (($body === null || $body === '') && $hasAttachments) {
            $preview = 'Изображение';
        } elseif ($body !== null && $body !== '') {
            $preview = mb_strlen($body) > 80 ? mb_substr($body, 0, 80).'…' : $body;
        } else {
            $preview = '';
        }

        return [
            'preview' => $preview,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
