<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MessageAttachment extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'message_id',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'access_token',
        'token_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function refreshAccessToken(int $ttlHours = 24): void
    {
        $this->access_token = Str::random(64);
        $this->token_expires_at = now()->addHours($ttlHours);
        $this->save();
    }

    public function ensureValidToken(int $ttlHours = 24): void
    {
        if ($this->token_expires_at === null || $this->token_expires_at->isPast()) {
            $this->refreshAccessToken($ttlHours);
        }
    }

    public function url(): string
    {
        return url("/api/chat/files/{$this->id}/{$this->access_token}");
    }
}
