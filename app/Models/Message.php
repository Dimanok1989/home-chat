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

    /**
     * @return array{preview: string, user_name: string|null, created_at: string|null}
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
            'user_name' => $message->user?->name,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
