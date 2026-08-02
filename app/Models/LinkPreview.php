<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LinkPreview extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'url',
        'title',
        'description',
        'image_url',
        'image_disk',
        'image_path',
        'image_mime_type',
        'image_access_token',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * @return array{id: int, url: string, title: string|null, description: string|null, image_url: string|null}
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'title' => $this->title,
            'description' => $this->description,
            'image_url' => $this->displayImageUrl(),
        ];
    }

    public function displayImageUrl(): ?string
    {
        if ($this->hasLocalImage()) {
            $diskName = $this->image_disk ?? 'local';
            $disk = \Illuminate\Support\Facades\Storage::disk($diskName);

            if ($disk->exists($this->image_path)) {
                return $this->localImageUrl();
            }
        }

        return $this->image_url;
    }

    public function localImageUrl(): ?string
    {
        if ($this->image_path === null || $this->image_access_token === null) {
            return null;
        }

        return url("/api/chat/link-previews/{$this->id}/image/{$this->image_access_token}");
    }

    public function hasLocalImage(): bool
    {
        return $this->image_path !== null && $this->image_access_token !== null;
    }
}
