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

        return $other?->name ?? 'Диалог';
    }
}
