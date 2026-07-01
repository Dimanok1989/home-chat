<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'last_name', 'username', 'email', 'password', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function chatRooms(): BelongsToMany
    {
        return $this->belongsToMany(ChatRoom::class, 'chat_room_user')
            ->withTimestamps();
    }

    public function displayName(): string
    {
        $parts = array_filter([$this->name, $this->last_name]);

        return implode(' ', $parts);
    }

    public function initial(): string
    {
        $name = trim($this->name ?? '');

        if ($name === '') {
            return '?';
        }

        return mb_strtoupper(mb_substr($name, 0, 1));
    }

    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return route('users.avatar', $this).'?v='.($this->updated_at?->timestamp ?? 0);
    }

    /**
     * @return array{id: int, display_name: string, has_avatar: bool, avatar_url: string|null, initial: string}
     */
    public function toPeerPayload(): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->displayName(),
            'has_avatar' => (bool) $this->avatar_path,
            'avatar_url' => $this->avatarUrl(),
            'initial' => $this->initial(),
        ];
    }
}
