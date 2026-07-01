<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ChatRoom extends Model
{
    public const TYPE_GLOBAL = 'global';

    public const TYPE_DIRECT = 'direct';

    public const TYPE_GROUP = 'group';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'type',
        'name',
        'direct_hash',
        'last_message_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_room_user')
            ->withPivot(['cleared_at', 'last_read_message_id'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @param  Builder<ChatRoom>  $query
     * @return Builder<ChatRoom>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $inner) use ($userId) {
            $inner->where('type', self::TYPE_GLOBAL)
                ->orWhereHas('users', fn (Builder $users) => $users->where('users.id', $userId));
        });
    }

    /**
     * @param  Builder<ChatRoom>  $query
     * @return Builder<ChatRoom>
     */
    public function scopeVisibleForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function (Builder $inner) use ($userId) {
            $inner->whereIn('type', [self::TYPE_GLOBAL, self::TYPE_GROUP])
                ->orWhere(function (Builder $direct) use ($userId) {
                    $direct->where('type', self::TYPE_DIRECT)
                        ->whereNotNull('last_message_at')
                        ->whereHas('users', function (Builder $users) use ($userId) {
                            $users->where('users.id', $userId)
                                ->where(function (Builder $pivot) {
                                    $pivot->whereNull('chat_room_user.cleared_at')
                                        ->orWhereColumn('chat_rooms.last_message_at', '>', 'chat_room_user.cleared_at');
                                });
                        });
                });
        });
    }

    public function clearedAtForUser(int $userId): ?\Illuminate\Support\Carbon
    {
        $pivot = $this->users()->where('users.id', $userId)->first()?->pivot;

        if ($pivot?->cleared_at === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($pivot->cleared_at);
    }

    public function ensureMembership(int $userId): void
    {
        if ($this->type === self::TYPE_GLOBAL) {
            $this->users()->syncWithoutDetaching([$userId]);
        }
    }

    public function lastReadMessageIdFor(int $userId): ?int
    {
        $this->ensureMembership($userId);

        $pivot = $this->users()->where('users.id', $userId)->first()?->pivot;

        if ($pivot?->last_read_message_id === null) {
            return null;
        }

        return (int) $pivot->last_read_message_id;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Message>
     */
    public function unreadMessagesQuery(int $userId): \Illuminate\Database\Eloquent\Builder
    {
        $clearedAt = $this->clearedAtForUser($userId);
        $lastReadId = $this->lastReadMessageIdFor($userId) ?? 0;

        $query = Message::query()
            ->where('chat_room_id', $this->id)
            ->where('id', '>', $lastReadId)
            ->where('user_id', '!=', $userId);

        if ($clearedAt !== null) {
            $query->where('created_at', '>', $clearedAt);
        }

        return $query;
    }

    public function unreadCountFor(int $userId): int
    {
        return $this->unreadMessagesQuery($userId)->count();
    }

    public function firstUnreadMessageIdFor(int $userId): ?int
    {
        $id = $this->unreadMessagesQuery($userId)
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function markReadUpTo(int $userId, int $messageId): void
    {
        if ($this->type === self::TYPE_GLOBAL) {
            $this->users()->syncWithoutDetaching([$userId]);
        }

        $message = Message::query()
            ->where('id', $messageId)
            ->where('chat_room_id', $this->id)
            ->first();

        if ($message === null) {
            return;
        }

        $currentLastRead = $this->lastReadMessageIdFor($userId) ?? 0;

        if ($messageId <= $currentLastRead) {
            return;
        }

        $this->users()->updateExistingPivot($userId, [
            'last_read_message_id' => $messageId,
        ]);
    }

    /**
     * @return list<int>
     */
    public function memberUserIds(): array
    {
        if ($this->type === self::TYPE_GLOBAL) {
            return User::query()->pluck('id')->all();
        }

        return $this->users()->pluck('users.id')->all();
    }

    /**
     * @param  Builder<ChatRoom>  $query
     * @return Builder<ChatRoom>
     */
    public function scopeGlobal(Builder $query): Builder
    {
        return $query->where('type', self::TYPE_GLOBAL);
    }

    public static function directHash(int $userA, int $userB): string
    {
        $min = min($userA, $userB);
        $max = max($userA, $userB);

        return $min.'-'.$max;
    }

    public static function findOrCreateDirect(int $userA, int $userB): self
    {
        if ($userA === $userB) {
            throw new \InvalidArgumentException('Cannot create a direct room with yourself.');
        }

        $hash = self::directHash($userA, $userB);

        $existing = self::query()->where('direct_hash', $hash)->first();

        if ($existing !== null) {
            $existing->users()->syncWithoutDetaching([$userA, $userB]);

            return $existing;
        }

        return DB::transaction(function () use ($hash, $userA, $userB) {
            $room = self::query()->create([
                'type' => self::TYPE_DIRECT,
                'name' => null,
                'direct_hash' => $hash,
            ]);

            $room->users()->attach([$userA, $userB]);

            return $room;
        });
    }

    /**
     * @param  list<int>  $userIds
     */
    public static function createGroup(string $name, array $userIds, int $creatorId): self
    {
        $memberIds = collect($userIds)
            ->push($creatorId)
            ->unique()
            ->values()
            ->all();

        return DB::transaction(function () use ($name, $memberIds) {
            $room = self::query()->create([
                'type' => self::TYPE_GROUP,
                'name' => $name,
                'direct_hash' => null,
            ]);

            $room->users()->attach($memberIds);

            return $room;
        });
    }

    public function isAccessibleBy(User $user): bool
    {
        if ($this->type === self::TYPE_GLOBAL) {
            return true;
        }

        return $this->users()->where('users.id', $user->id)->exists();
    }

    public function titleFor(User $user): string
    {
        if ($this->type === self::TYPE_GLOBAL || $this->type === self::TYPE_GROUP) {
            return $this->name ?? 'Чат';
        }

        $other = $this->users()
            ->where('users.id', '!=', $user->id)
            ->first();

        return $other?->displayName() ?? 'Диалог';
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(User $user): array
    {
        $clearedAt = $this->clearedAtForUser($user->id);

        $lastMessageQuery = Message::query()
            ->where('chat_room_id', $this->id)
            ->with(['attachments', 'user'])
            ->latest('id')
            ->limit(1);

        if ($clearedAt !== null) {
            $lastMessageQuery->where('created_at', '>', $clearedAt);
        }

        $lastMessage = $lastMessageQuery->first();
        $peer = null;

        if ($this->type === self::TYPE_DIRECT) {
            $other = $this->users->firstWhere('id', '!=', $user->id);
            $peer = $other?->toPeerPayload();
        }

        $this->ensureMembership($user->id);

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->titleFor($user),
            'peer' => $peer,
            'last_message' => $lastMessage ? Message::previewPayload($lastMessage) : null,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'unread_count' => $this->unreadCountFor($user->id),
            'last_read_message_id' => $this->lastReadMessageIdFor($user->id),
        ];
    }
}
