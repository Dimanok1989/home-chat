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
            'image_url' => $this->image_url,
        ];
    }
}
