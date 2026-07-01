<?php

namespace App\Http\Controllers;

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserAvatarController extends Controller
{
    public function show(User $user): BinaryFileResponse
    {
        /** @var User $viewer */
        $viewer = Auth::user();

        if (! $this->canViewAvatar($viewer, $user)) {
            abort(403);
        }

        if (! $user->avatar_path) {
            abort(404);
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($user->avatar_path)) {
            abort(404);
        }

        $mimeType = $disk->mimeType($user->avatar_path) ?: 'image/jpeg';

        return response()->file(
            $disk->path($user->avatar_path),
            [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'private, max-age=3600',
            ],
        );
    }

    private function canViewAvatar(User $viewer, User $target): bool
    {
        if ($viewer->id === $target->id) {
            return true;
        }

        return ChatRoom::query()
            ->forUser($viewer->id)
            ->whereHas('users', fn ($query) => $query->where('users.id', $target->id))
            ->exists();
    }
}
